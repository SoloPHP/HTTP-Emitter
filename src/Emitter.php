<?php declare(strict_types=1);

namespace Solo\HttpEmitter;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Emitter
{
    public function __construct(
        private readonly int $responseChunkSize = 4096
    ) {
    }

    public function emit(ResponseInterface $response): void
    {
        $isEmpty = $this->isResponseEmpty($response);
        if (headers_sent() === false) {
            $this->emitHeaders($response);
            $this->emitStatusLine($response);
        }

        if (!$isEmpty) {
            $this->emitBody($response);
        }
    }

    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $isSetCookie = strtolower($name) === 'set-cookie';
            foreach ($values as $index => $value) {
                $header = sprintf('%s: %s', $name, $value);
                header($header, $index === 0 && !$isSetCookie);
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

        $contentLength = $response->getHeaderLine('Content-Length');
        $amountToRead = $contentLength ? (int) $contentLength : $body->getSize();

        if ($amountToRead && $amountToRead > 0) {
            $this->emitBodyWithLength($body, $amountToRead);
        } else {
            $this->emitBodyWithoutLength($body);
        }
    }

    private function emitBodyWithLength(StreamInterface $body, int $amountToRead): void
    {
        while ($amountToRead > 0 && !$body->eof()) {
            $length = min($this->responseChunkSize, $amountToRead);
            $data = $body->read($length);

            if ($data === '') {
                break;
            }

            echo $data;
            $amountToRead -= strlen($data);

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
    }

    private function emitBodyWithoutLength(StreamInterface $body): void
    {
        while (!$body->eof()) {
            $data = $body->read($this->responseChunkSize);

            if ($data === '') {
                break;
            }

            echo $data;

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
    }

    public function isResponseEmpty(ResponseInterface $response): bool
    {
        $statusCode = $response->getStatusCode();
        if (in_array($statusCode, [204, 205, 304], true)) {
            return true;
        }

        $stream = $response->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
            return $stream->read(1) === '';
        }

        return $stream->eof();
    }
} 