<?php

declare(strict_types=1);

namespace Solo\HttpEmitter\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Solo\HttpEmitter\Emitter;
use Solo\HttpEmitter\EmitterTestState;

class EmitterTest extends TestCase
{
    private Emitter $emitter;

    protected function setUp(): void
    {
        EmitterTestState::reset();
        $this->emitter = new Emitter();
    }

    protected function tearDown(): void
    {
        EmitterTestState::reset();
    }

    public function testEmitWithHeadersAlreadySent(): void
    {
        EmitterTestState::$headersSent = true;

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('read')->willReturn('');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaders')->willReturn([]);

        $this->emitter->emit($response);

        $this->assertEmpty(EmitterTestState::$headers);
    }

    public function testEmitHeaders(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('read')->willReturn('');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getReasonPhrase')->willReturn('OK');
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaders')->willReturn([
            'Content-Type' => ['application/json'],
            'X-Custom' => ['value1', 'value2'],
        ]);

        $this->emitter->emit($response);

        $headers = EmitterTestState::$headers;
        $this->assertCount(4, $headers);

        $this->assertEquals('Content-Type: application/json', $headers[0]['header']);
        $this->assertTrue($headers[0]['replace']);

        $this->assertEquals('X-Custom: value1', $headers[1]['header']);
        $this->assertTrue($headers[1]['replace']);

        $this->assertEquals('X-Custom: value2', $headers[2]['header']);
        $this->assertFalse($headers[2]['replace']);
    }

    public function testEmitSetCookieHeaders(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('read')->willReturn('');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getReasonPhrase')->willReturn('OK');
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaders')->willReturn([
            'Set-Cookie' => ['cookie1=value1', 'cookie2=value2'],
        ]);

        $this->emitter->emit($response);

        $headers = EmitterTestState::$headers;

        $cookieHeaders = array_filter($headers, fn($h) => str_starts_with($h['header'], 'Set-Cookie'));
        foreach ($cookieHeaders as $header) {
            $this->assertFalse($header['replace']);
        }
    }

    public function testEmitBodyWithNonSeekableStream(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->method('eof')->willReturnOnConsecutiveCalls(false, false, true);
        $stream->method('read')->willReturnOnConsecutiveCalls('test', '');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getReasonPhrase')->willReturn('OK');
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getHeaderLine')->willReturn('');

        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();

        $this->assertEquals('test', $output);
    }

    public function testEmitBodyWithoutContentLengthAndNullSize(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('getSize')->willReturn(null);
        $stream->method('eof')->willReturnOnConsecutiveCalls(false, false, false, true);
        $stream->method('read')->willReturnOnConsecutiveCalls('x', 'hello', '');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getReasonPhrase')->willReturn('OK');
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getHeaderLine')->willReturn('');

        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();

        $this->assertEquals('hello', $output);
    }

    public function testEmitBodyStopsOnConnectionAbort(): void
    {
        EmitterTestState::$connectionStatus = CONNECTION_ABORTED;

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('getSize')->willReturn(1000);
        $stream->method('eof')->willReturn(false);
        $stream->method('read')->willReturn('data');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getReasonPhrase')->willReturn('OK');
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getHeaderLine')->willReturn('1000');

        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();

        $this->assertEquals('data', $output);
    }

    public function testEmitBodyWithoutLengthStopsOnConnectionAbort(): void
    {
        EmitterTestState::$connectionStatus = CONNECTION_ABORTED;

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('getSize')->willReturn(null);
        $stream->method('eof')->willReturnOnConsecutiveCalls(false, false, false);
        $stream->method('read')->willReturnOnConsecutiveCalls('x', 'chunk', 'more');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getReasonPhrase')->willReturn('OK');
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getHeaderLine')->willReturn('');

        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();

        $this->assertEquals('chunk', $output);
    }

    public function testEmitBodyWithLengthStopsOnEmptyData(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('eof')->willReturn(false);
        $stream->method('read')->willReturnOnConsecutiveCalls('d', 'data', '');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getReasonPhrase')->willReturn('OK');
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getHeaderLine')->willReturn('1000');

        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();

        $this->assertEquals('data', $output);
    }

    public function testEmitWith204StatusDoesNotEmitBody(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->never())->method('read');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(204);
        $response->method('getReasonPhrase')->willReturn('No Content');
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaders')->willReturn([]);

        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();

        $this->assertEquals('', $output);
    }
}
