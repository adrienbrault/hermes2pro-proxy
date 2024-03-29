<?php

use AdrienBrault\Hermes2Pro\Hermes2ProPlugin;
use Http\Client\Common\PluginClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

require __DIR__ . '/../vendor/autoload.php';

$psrHttpFactory = new PsrHttpFactory();
$httpFoundationFactory = new HttpFoundationFactory();

$client = new PluginClient(
    Psr18ClientDiscovery::find(),
    [
        new Hermes2ProPlugin(),
    ]
);

$request = Request::createFromGlobals();

$targetUri = Psr17FactoryDiscovery::findUriFactory()
    ->createUri(
        getenv('OPENAI_BASE_URI')
        . $request->getRequestUri()
    );

$subRequest = $psrHttpFactory->createRequest($request)
    ->withUri($targetUri);

$psrResponse = $client->sendRequest(
    $subRequest
);

$response = $httpFoundationFactory->createResponse($psrResponse);
$response->headers->remove('Content-Length');
$response->send();
