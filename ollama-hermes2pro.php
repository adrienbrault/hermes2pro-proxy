<?php

use AdrienBrault\Hermes2Pro\Hermes2ProPlugin;
use Http\Client\Common\PluginClient;
use Http\Discovery\Psr18ClientDiscovery;

require_once __DIR__ . '/vendor/autoload.php';

$client = OpenAI::factory()
    ->withApiKey(getenv('OPENAI_API_KEY') ?? 'sk-xxx')
    ->withBaseUri(getenv('OLLAMA_HOST') ?: 'http://localhost:11434/v1')
    ->withHttpClient(
        new PluginClient(
            Psr18ClientDiscovery::find(),
            [
                new Hermes2ProPlugin()
            ]
        )
    )
    ->make()
;

$request = require __DIR__ . '/request.php';
$result = $client->chat()->create([
    'model' => 'adrienbrault/nous-hermes2pro:Q2_K',
    ...$request,
]);

dump($result->choices[0]->message);
