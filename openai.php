<?php

require_once(__DIR__ . '/vendor/autoload.php');

$client = OpenAI::client(
    getenv('OPENAI_API_KEY')
);

$request = require __DIR__ . '/request.php';

$result = $client->chat()->create([
    'model' => 'gpt-3.5-turbo',
    ...$request,
]);

dump($result->choices[0]->message);
