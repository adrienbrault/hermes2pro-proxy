<?php

use AdrienBrault\Hermes2Pro\Hermes2ProPlugin;
use Http\Client\Common\PluginClient;
use Http\Discovery\Psr18ClientDiscovery;

require_once __DIR__ . '/../vendor/autoload.php';

$client = OpenAI::factory()
    ->withApiKey('sk-xxx')
    ->withBaseUri(
        sprintf(
            '%s/v1',
            getenv('OLLAMA_HOST') ?: 'http://localhost:11434'
        )
    )
    ->withHttpClient(
        new PluginClient(
            Psr18ClientDiscovery::find(),
            [
                new Hermes2ProPlugin(),
                require __DIR__ . '/demo-logger.php',
            ]
        )
    )
    ->make()
;

$demo = require __DIR__ . '/demo.php';
$demo($client, 'adrienbrault/nous-hermes2pro:Q4_K_M');
