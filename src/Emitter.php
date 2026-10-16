<?php

declare(strict_types=1);

namespace Solo\HttpEmitter;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Solo\Contracts\Http\EmitterInterface;

class Emitter implements EmitterInterface
{
    public function __construct(
        private readonly int $responseChunkSize = 4096
    ) {
        if ($responseChunkSize < 1) {
            throw new InvalidArgumentException('Response chunk size must be greater than 0');
        }
    }

    public function emit(ResponseInterface $response): void
    {
        if (headers_sent() === false) {
            $this->emitHeaders($response);
            // Status line goes last: a Location header would otherwise reset the status to 302
            $this->emitStatusLine($response);
        }

        if (!$this->isBodilessStatus($response->getStatusCode())) {
            $this->emitBody($response);
        }
    }

    public function isResponseEmpty(ResponseInterface $response): bool
    {
        if ($this->isBodilessStatus($response->getStatusCode())) {
            return true;
        }

        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
            return $body->read(1) === '';
        }

        return $body->eof();
    }

    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            // Numeric header names come back from getHeaders() as int keys
            $name = (string) $name;
            $replace = strcasecmp($name, 'Set-Cookie') !== 0;

            foreach ($values as $value) {
                header($name . ': ' . $value, $replace);
                $replace = false;
            }
        }
    }

    private function emitStatusLine(ResponseInterface $response): void
    {
        $statusLine = sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );
        header($statusLine, true, $response->getStatusCode());
    }

    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $remaining = $this->getContentLength($response) ?? PHP_INT_MAX;

        while ($remaining > 0 && !$body->eof()) {
            $data = $body->read(min($this->responseChunkSize, $remaining));

            if ($data === '') {
                break;
            }

            echo $data;
            $remaining -= strlen($data);

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
    }

    private function getContentLength(ResponseInterface $response): ?int
    {
        $contentLength = $response->getHeaderLine('Content-Length');

        return preg_match('/^\d+$/D', $contentLength) === 1 ? (int) $contentLength : null;
    }

    private function isBodilessStatus(int $statusCode): bool
    {
        // RFC 9110: 1xx, 204, 205 and 304 responses never carry content
        return $statusCode < 200 || in_array($statusCode, [204, 205, 304], true);
    }
}
