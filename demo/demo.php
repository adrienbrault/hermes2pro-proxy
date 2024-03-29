<?php

use function Psl\Json\decode;
use function Psl\Json\encode;
use function Psl\Vec\filter;

$tools = [
    [
        'type' => 'function',
        'function' => [
            'name' => 'get_current_time',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'timezone' => [
                        'type' => 'string',
                        'description' => 'The timezone to get the current time for. Example: "America/Los_Angeles" or "Asia/Tokyo"'
                    ]
                ],
                'required' => ['timezone']
            ]
        ]
    ]
];

return function (OpenAI\Client $client, string $model) use ($tools) {
    $userPrompt = 'What time is it in france? What about new york city?';

    $validArgs = filter(array_slice($_SERVER['argv'], 1), fn($arg) => !str_starts_with($arg, '-'));
    $userPrompt = $validArgs === [] ? $userPrompt : implode(' ', $validArgs);

    $messages = [
        [
            'role' => 'system',
            'content' => 'Answer the user question succinctly.',
        ],
        [
            'role' => 'user',
            'content' => $userPrompt
        ],
    ];

    $result = $client->chat()->create([
        'model' => $model,
        'messages' => $messages,
        'tools' => $tools,
    ]);

    $messages[] = $result->choices[0]->message->toArray();

    // Invoke functions and feed back results
    foreach ($result->choices[0]->message->toolCalls as $toolCall) {
        assert($toolCall->function->name === 'get_current_time');

        $timezone = decode($toolCall->function->arguments)['timezone'];

        $messages[] = [
            'role' => 'tool',
            'tool_call_id' => $toolCall->id,
            'name' => $toolCall->function->name,
            'content' => (new DateTime('now', new DateTimeZone($timezone)))->format('H:i:s'),
        ];
    }

    $result = $client->chat()->create([
        'model' => $model,
        'messages' => $messages,
        'tools' => $tools,
    ]);

    dump($result->choices[0]->message->content);
};