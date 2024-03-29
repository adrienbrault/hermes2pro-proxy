<?php

namespace AdrienBrault\Hermes2Pro\Tests;

use AdrienBrault\Hermes2Pro\Hermes2ProPlugin;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Promise\FulfilledPromise;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function Psl\Json\decode;
use function Psl\Json\encode;

/**
 * @covers Hermes2ProPlugin
 */
class Hermes2ProPluginTest extends TestCase
{
    public function getTestNoopData(): iterable
    {
        yield [
            new Request('POST', '/api/v1/chat/completions', [], json_encode([
                'model' => 'adrienbrault/nous-hermes2pro:Q2_K',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Hello, how are you?',
                    ],
                ],
            ])),
        ];
    }

    /**
     * @dataProvider getTestNoopData
     */
    public function testNoop(RequestInterface $request): void
    {
        $plugin = new Hermes2ProPlugin();

        $response = $plugin->handleRequest(
            $request,
            $this->mockNext(
                function (RequestInterface $nextRequest) use ($request) {
                    $this->assertSame($request, $nextRequest); // no changes
                },
                $mockedResponse = new Response(200, [], encode([
                    'model' => 'adrienbrault/nous-hermes2pro:Q2_K',
                    'choices' => [
                        [
                            'role' => 'assistant',
                            'content' => 'I am fine, thank you.',
                        ],
                    ],
                ]))
            ),
            $this->mockFirst()
        )->wait();

        $this->assertSame(
            $mockedResponse->getBody()->getContents(),
            $response->getBody()->getContents()
        );
    }

    public function testTools()
    {
        $plugin = new Hermes2ProPlugin();

        $request = new Request('POST', '/api/v1/chat/completions', [], json_encode([
            'model' => 'adrienbrault/nous-hermes2pro:Q2_K',
            'messages' => [
                $firstMessage = [
                    'role' => 'user',
                    'content' => 'What time is it in france? In NYC?',
                ],
            ],
            'tools' => [
                $this->getTimeTool(),
            ],
        ]));

        $mockedResponse = new Response(200, [], json_encode([
            'model' => 'adrienbrault/nous-hermes2pro:Q2_K',
            'choices' => [
                [
                    'message' => [
                        'content' => \Psl\Str\join([
                            '<tool_call>{"name": "get_current_time", "arguments": {"location": "france"}}</tool_call>',
                            '<tool_call>{"name": "get_current_time", "arguments": {"location": "NYC"}}</tool_call>',
                        ], "\n"),
                    ],
                ],
            ],
        ]));

        $response = $plugin->handleRequest(
            $request,
            $this->mockNext(
                function (RequestInterface $nextRequest) use ($request, $firstMessage) {
                    $payload = decode($nextRequest->getBody()->getContents(), true);
                    $nextRequest->getBody()->rewind(); // Make sure the body is still readable

                    $this->assertArrayNotHasKey('tools', $payload);
                    $this->assertSame('system', $payload['messages'][0]['role']);
                    $this->assertStringContainsString(
                        encode($this->getTimeTool()),
                        $payload['messages'][0]['content']
                    );
                    $this->assertStringContainsString(
                        'You are provided with function signatures within <tools></tools> XML tags',
                        $payload['messages'][0]['content']
                    );
                    $this->assertSame($firstMessage, $payload['messages'][1]);
                },
                $mockedResponse
            ),
            $this->mockFirst()
        )->wait();

        $responsePayload = decode($response->getBody()->getContents());

        $this->assertSame('', $responsePayload['choices'][0]['message']['content']);
        $this->assertCount(2, $responsePayload['choices'][0]['message']['tool_calls']);
        $this->assertEquals(
            'function',
            $responsePayload['choices'][0]['message']['tool_calls'][0]['type']
        );
        $this->assertEquals(
            [
                'name' => 'get_current_time',
                'arguments' => '{"location":"france"}',
            ],
            $responsePayload['choices'][0]['message']['tool_calls'][0]['function']
        );
        $this->assertEquals(
            'function',
            $responsePayload['choices'][0]['message']['tool_calls'][1]['type']
        );
        $this->assertEquals(
            [
                'name' => 'get_current_time',
                'arguments' => '{"location":"NYC"}',
            ],
            $responsePayload['choices'][0]['message']['tool_calls'][1]['function']
        );
    }

    public function testToolCalls()
    {
        $plugin = new Hermes2ProPlugin();

        $request = new Request('POST', '/api/v1/chat/completions', [], json_encode([
            'model' => 'adrienbrault/nous-hermes2pro:Q2_K',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'What time is it in france? In NYC?',
                ],
                [
                    'role' => 'tool',
                    'tool_call_id' => 'call_123',
                    'name' => 'get_current_time',
                    'content' => '{"time":"12:34:56"}',
                ],
            ],
            'tools' => [
                $this->getTimeTool(),
            ],
        ]));

        $mockedResponse = new Response(200, [], json_encode([]));

        $plugin->handleRequest(
            $request,
            $this->mockNext(
                function (RequestInterface $nextRequest) {
                    $payload = decode($nextRequest->getBody()->getContents(), true);
                    $nextRequest->getBody()->rewind(); // Make sure the body is still readable

                    $this->assertSame([
                        'role' => 'system',
                        'content' => '<tool_response>{"time":"12:34:56"}</tool_response>',
                    ], $payload['messages'][2]);
                },
                $mockedResponse
            ),
            $this->mockFirst()
        )->wait();
    }

    public function getTimeTool(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_current_time',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['location'],
                ],
            ],
        ];
    }

    private function mockFirst(): callable
    {
        // do nothing ...
        return function () {
        };
    }

    private function mockNext(callable $assertRequest, ResponseInterface $response)
    {
        return function (RequestInterface $request) use ($assertRequest, $response) {
            $assertRequest($request);

            return new FulfilledPromise($response);
        };
    }
}
