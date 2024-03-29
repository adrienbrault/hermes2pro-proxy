<?php

namespace AdrienBrault\Hermes2Pro;

use function Psl\Json\decode;
use function Psl\Json\encode;
use function Psl\Regex\every_match;
use function Psl\Regex\replace;
use function Psl\Type\mixed_dict;
use function Psl\Type\optional;
use function Psl\Type\shape;
use function Psl\Type\string;
use function Psl\Type\vec;
use function Psl\Vec\filter_nulls;
use function Psl\Vec\map;
use function Psl\SecureRandom\string as randomString;


class Converter
{
    public function convertRequest(string $body): ?string
    {
        $payload = decode($body, true);

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

        return encode($payload);
    }

    public function convertResponse(string $body): ?string
    {
        $payload = decode($body);

        if (!array_key_exists('choices', $payload)
            || !is_array($payload['choices']) === 0
            || !str_starts_with($payload['model'] ?? '', 'adrienbrault/nous-hermes2pro')
        ) {
            return null;
        }

        $payload['choices'] = map(
            $payload['choices'],
            static function (array $choice): array {
                if (!is_array($choice['message'] ?? null)) {
                    return $choice;
                }

                $matches = map(
                    every_match(
                        $choice['message']['content'] ?? '',
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

                $choice['message']['tool_calls'] = $toolCalls;
                $choice['message']['content'] = replace(
                    $choice['message']['content'],
                    '#<tool_call>.*?</tool_call>\s*#si',
                    ''
                );

                return $choice;
            }
        );



        return encode($payload);
    }
}