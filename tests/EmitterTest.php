<?php

declare(strict_types=1);

namespace Solo\HttpEmitter\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Solo\Contracts\Http\EmitterInterface;
use Solo\HttpEmitter\Emitter;
use Solo\HttpEmitter\EmitterTestState;

final class EmitterTest extends TestCase
{
    protected function setUp(): void
    {
        EmitterTestState::reset();
    }

    public function testImplementsEmitterContract(): void
    {
        $this->assertInstanceOf(EmitterInterface::class, new Emitter());
    }

    public function testRejectsNonPositiveChunkSize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Emitter(0);
    }

    public function testEmitsStatusLineAfterHeaders(): void
    {
        $this->emit(new Response(202, ['Location' => '/jobs/1']));

        $this->assertSame([
            ['header' => 'Location: /jobs/1', 'replace' => true, 'code' => 0],
            ['header' => 'HTTP/1.1 202 Accepted', 'replace' => true, 'code' => 202],
        ], EmitterTestState::$headers);
    }

    public function testRepeatedHeaderValuesAreAppended(): void
    {
        $this->emit(new Response(200, ['X-Custom' => ['a', 'b']]));

        $this->assertSame([
            ['header' => 'X-Custom: a', 'replace' => true, 'code' => 0],
            ['header' => 'X-Custom: b', 'replace' => false, 'code' => 0],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'code' => 200],
        ], EmitterTestState::$headers);
    }

    public function testSetCookieNeverReplacesExistingCookies(): void
    {
        $this->emit(new Response(200, ['set-cookie' => ['a=1', 'b=2']]));

        $this->assertSame([
            ['header' => 'set-cookie: a=1', 'replace' => false, 'code' => 0],
            ['header' => 'set-cookie: b=2', 'replace' => false, 'code' => 0],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'code' => 200],
        ], EmitterTestState::$headers);
    }

    public function testNumericHeaderName(): void
    {
        $this->emit(new Response(200, ['123' => 'value']));

        $this->assertSame([
            ['header' => '123: value', 'replace' => true, 'code' => 0],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'code' => 200],
        ], EmitterTestState::$headers);
    }

    public function testHeadersAreSkippedWhenAlreadySent(): void
    {
        EmitterTestState::$headersSent = true;

        $output = $this->emit(new Response(200, ['X-Custom' => 'a'], 'body'));

        $this->assertSame([], EmitterTestState::$headers);
        $this->assertSame('body', $output);
    }

    public function testBodyIsRewoundAndEmittedInChunks(): void
    {
        $body = Stream::create('hello world');
        $body->read(5);

        $this->assertSame('hello world', $this->emit(new Response(200, [], $body), new Emitter(3)));
    }

    #[TestWith(['5', 'hello'])]
    #[TestWith(['0', ''])]
    #[TestWith(['abc', 'hello world'])]
    #[TestWith(['-1', 'hello world'])]
    #[TestWith(['1.5', 'hello world'])]
    #[TestWith(['2, 2', 'hello world'])]
    public function testContentLengthLimitsBody(string $contentLength, string $expected): void
    {
        $response = new Response(200, ['Content-Length' => $contentLength], 'hello world');

        $this->assertSame($expected, $this->emit($response, new Emitter(2)));
    }

    #[TestWith([100])]
    #[TestWith([204])]
    #[TestWith([205])]
    #[TestWith([304])]
    public function testBodilessStatusSkipsBody(int $statusCode): void
    {
        $this->assertSame('', $this->emit(new Response($statusCode, [], 'ignored')));
    }

    public function testNonSeekableBodyIsNotRewound(): void
    {
        $domain = PHP_OS_FAMILY === 'Windows' ? STREAM_PF_INET : STREAM_PF_UNIX;
        [$reader, $writer] = stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)
            ?: $this->fail('Unable to create a socket pair');
        fwrite($writer, 'foobar');
        fclose($writer);

        // A real socket stream: Nyholm throws if the emitter tries to rewind or seek it
        $this->assertSame('foobar', $this->emit(new Response(200, [], Stream::create($reader))));
    }

    public function testStopsWhenBodyReturnsNoData(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body->method('eof')->willReturn(false);
        $body->expects($this->once())->method('read')->willReturn('');

        $this->assertSame('', $this->emit(new Response(200, [], $body)));
    }

    public function testStopsWhenClientDisconnects(): void
    {
        EmitterTestState::$connectionStatus = CONNECTION_ABORTED;

        $this->assertSame('he', $this->emit(new Response(200, [], 'hello'), new Emitter(2)));
    }

    #[DataProvider('emptyResponseProvider')]
    public function testIsResponseEmpty(ResponseInterface $response, bool $expected): void
    {
        $this->assertSame($expected, (new Emitter())->isResponseEmpty($response));
    }

    /**
     * @return array<string, array{ResponseInterface, bool}>
     */
    public static function emptyResponseProvider(): array
    {
        $exhaustedStream = self::createConfiguredStub(StreamInterface::class, ['eof' => true]);

        return [
            'informational status' => [new Response(100, [], 'body'), true],
            'bodiless status' => [new Response(204, [], 'body'), true],
            'empty body' => [new Response(200), true],
            'exhausted non-seekable body' => [new Response(200, [], $exhaustedStream), true],
            'non-empty body' => [new Response(200, [], 'body'), false],
        ];
    }

    private function emit(ResponseInterface $response, ?Emitter $emitter = null): string
    {
        ($emitter ?? new Emitter())->emit($response);

        return $this->getActualOutputForAssertion();
    }
}
