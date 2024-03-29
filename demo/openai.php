<?php

use Http\Client\Common\PluginClient;
use Http\Discovery\Psr18ClientDiscovery;

require_once(__DIR__ . '/../vendor/autoload.php');

$client = OpenAI::factory()
    ->withApiKey(getenv('OPENAI_API_KEY'))
    ->withBaseUri(getenv('OPENAI_BASE_URI') ?: 'https://api.openai.com/v1')
    ->withHttpClient(
        new PluginClient(
            Psr18ClientDiscovery::find(),
            [
                require __DIR__ . '/demo-logger.php',
            ]
        )
    )
    ->make()
;

$demo = require __DIR__ . '/demo.php';
$demo($client, getenv('MODEL') ?: 'gpt-3.5-turbo-0125');
