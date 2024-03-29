<?php

namespace AdrienBrault\Hermes2Pro;

use Http\Client\Common\Plugin;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class Hermes2ProPlugin implements Plugin
{
    private Converter $converter;
    private StreamFactoryInterface $streamFactory;

    public function __construct(
        Converter $converter = null,
        StreamFactoryInterface $streamFactory = null
    ) {
        $this->converter = $converter ?? new Converter();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        $request = $this->getHermes2ProRequest($request);

        return $next($request)->then(
            $this->getHermes2ProResponse(...)
        );
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function getHermes2ProRequest(RequestInterface $request): ?RequestInterface
    {
        if (!str_ends_with($request->getUri()->getPath(), '/chat/completions')) {
            return $request;
        }

        $body = $request->getBody()->getContents();
        $request->getBody()->rewind(); // Make sure the body is still readable

        $newBody = $this->converter->convertRequest($body);

        if (null === $newBody) {
            return $request;
        }

        return $request->withBody(
            $this->streamFactory->createStream($newBody)
        );
    }

    private function getHermes2ProResponse(ResponseInterface $response): ResponseInterface
    {
        $body = $response->getBody()->getContents();
        $response->getBody()->rewind(); // Make sure the body is still readable

        $newBody = $this->converter->convertResponse($body);

        if (null === $newBody) {
            return $response;
        }

        return $response->withBody(
            $this->streamFactory->createStream($newBody)
        );
    }
}