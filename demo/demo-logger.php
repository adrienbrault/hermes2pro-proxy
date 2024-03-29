<?php

use Http\Client\Common\Plugin\LoggerPlugin;
use Http\Message\Formatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

$formatter = new class() implements Formatter {
    #[\Override]
    public function formatRequest(RequestInterface $request)
    {
        $contents = $request->getBody()->getContents();
        $request->getBody()->rewind();

        return sprintf(
            "%s %s HTTP/%s\n%s",
            $request->getMethod(),
            $request->getUri(),
            $request->getProtocolVersion(),
            $contents
        );
    }

    #[\Override]
    public function formatResponse(ResponseInterface $response)
    {
        $contents = $response->getBody()->getContents();
        $response->getBody()->rewind();

        return sprintf(
            "HTTP/%s %s %s\n%s",
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $contents
        );
    }
};

$verbose = count(array_intersect(['-v', '-vv', '-vvv'], $_SERVER['argv'])) > 0;

return new LoggerPlugin(new Logger('http', [
    new StreamHandler('php://stderr', $verbose ? Level::Debug : Level::Error),
]), $formatter);
