<?php

namespace AdrienBrault\Hermes2Pro;

use Http\Client\Common\Plugin;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use function Psl\Json\decode;
use function Psl\Json\encode;
use function Psl\Regex\every_match;
use function Psl\Regex\replace;
use function Psl\SecureRandom\bytes;
use function Psl\Type\mixed_dict;
use function Psl\Type\optional;
use function Psl\Type\shape;
use function Psl\Type\string;
use function Psl\Type\vec;
use function Psl\Vec\filter_nulls;
use function Psl\Vec\map;
use function Psl\SecureRandom\string as randomString;

class Hermes2ProPlugin implements Plugin
{
    private StreamFactoryInterface $streamFactory;

    public function __construct(
        StreamFactoryInterface $streamFactory = null
    ) {
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        $newRequest = $this->getHermes2ProRequest($request);

        return $next($newRequest ?? $request)->then(
            fn (ResponseInterface $response) => null !== $newRequest
                ? $this->getHermes2ProResponse($newRequest, $response)
                : $response
        );
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function getHermes2ProRequest(RequestInterface $request): ?RequestInterface
    {
        if (!str_ends_with($request->getUri()->getPath(), '/chat/completions')) {
            return null;
        }

        $payload = decode($request->getBody()->getContents(), true);
        $request->getBody()->rewind(); // Make sure the body is still readable

        $shape = shape([
            'model' => string(),
            'messages' => vec(
                mixed_dict(),
            ),
            'tools' => optional(vec(mixed_dict())),
        ], true);

        if (!$shape->matches($payload)
            || !str_starts_with($payload['model'], 'adrienbrault/nous-hermes2pro')
        ) {
            return null;
        }

        $tools = $payload['tools'] ?? [];

        if ($tools === []) {
            return null;
        }

        $systemPrompt = implode(" ", [
            'You are a function calling AI model.',
            'You are provided with function signatures within <tools></tools> XML tags.',
            'You may call one or more functions to assist with the user query.',
            "Don't make assumptions about what values to plug into functions.",
            'Here are the available tools:',
            '<tools>',
            json_encode($tools),
            '</tools>',
            'Use the following json schema for each tool call you will make:',
            '{"type": "object", "properties": {"name": {"title": "Name", "type": "string"}, "arguments": {"title": "Arguments", "type": "object"}}, "required": ["arguments", "name"], "title": "FunctionCall"}',
            'For each function call return a json object with function name and arguments within <tool_call></tool_call> XML tags as follows:',
            '<tool_call>',
            '{"name": <function-name>, "arguments": <args-dict>}',
            '</tool_call>.',
            'Only reply with <tool_call>...</tool_call>, no other content.',
        ]);

        if ($payload['messages'][0]['role'] === 'system') {
            $payload['messages'][0]['content'] = \Psl\Str\join(
                [
                    $systemPrompt,
                    $payload['messages'][0]['content']
                ],
                " "
            );
        } else {
            $payload['messages'] = [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                ...$payload['messages'],
            ];
        }

        $payload['messages'] = map(
            $payload['messages'],
            static function (array $message): array {
                if ($message['role'] === 'tool') {
                    return [
                        'role' => 'system',
                        'content' => sprintf('<tool_response>%s</tool_response>', $message['content']),
                    ];
                }

                if ($message['role'] === 'assistant'
                    && count($message['tool_calls'] ?? []) > 0
                ) {
                    $content = \Psl\Str\join(
                        [
                            $message['content'],
                            ...map(
                                $message['tool_calls'],
                                static function (array $toolCall): string {
                                    return sprintf("<tool_call>\n%s\n</tool_call>", json_encode([
                                        'name' => $toolCall['function']['name'],
                                        'arguments' => decode($toolCall['function']['arguments'] ?? '[]'),
                                    ]));
                                }
                            )
                        ],
                        "\n"
                    );

                    return [
                        'role' => 'assistant',
                        'content' => \Psl\Str\trim($content)
                    ];
                }

                return $message;
            }
        );

        unset($payload['tools']);

        return $request->withBody(
            $this->streamFactory->createStream(
                encode($payload)
            )
        );
    }

    private function getHermes2ProResponse(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = decode($response->getBody()->getContents());
        $response->getBody()->rewind(); // Make sure the body is still readable

        if (!array_key_exists('choices', $payload) || !is_array($payload['choices']) === 0) {
            return $response;
        }

        $matches = map(
            every_match(
                $payload['choices'][0]['message']['content'] ?? '',
                '#<tool_call>\s*(?P<json>\{.*?\})\s*</tool_call>#s',
                shape([
                    'json' => string(),
                ])
            ) ?? [],
            static fn (array $match): string => $match['json']
        );

        $toolCalls = map(
            $matches,
            function ($jsonString) {
                $jsonString = trim((string) $jsonString);

                // In case the model adds junk at the end of the json
                // we try to remove it and parse the json again, and repeat
                $json = null;
                for ($i = 0; $i < 30; ++$i) {
                    $json = @json_decode($jsonString, true, \JSON_PARTIAL_OUTPUT_ON_ERROR);

                    if ($json !== null) {
                        break;
                    }

                    $jsonString = substr($jsonString, 0, -1);
                }

                if ($json === null) {
                    return null;
                }

                shape([
                    'name' => string(),
                    'arguments' => mixed_dict(),
                ])->assert($json);

                return [
                    'id' => 'call_' . randomString(24),
                    'type' => 'function',
                    'function' => [
                        'name' => $json['name'],
                        'arguments' => encode([
                            ...$json['arguments'],
                            ...array_diff_key($json, [
                                'name' => null,
                                'arguments' => null,
                            ]),
                        ]),
                    ],
                ];
            }
        );
        $toolCalls = filter_nulls($toolCalls);

        $payload['choices'][0]['message']['tool_calls'] = $toolCalls;
        $payload['choices'][0]['message']['content'] = replace(
            $payload['choices'][0]['message']['content'],
            '#<tool_call>.*?</tool_call>\s*#si',
            ''
        );

        return $response->withBody(
            $this->streamFactory->createStream(
                encode($payload)
            )
        );
    }
}