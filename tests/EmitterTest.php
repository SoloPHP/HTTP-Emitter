<?php declare(strict_types=1);

namespace Solo\HttpEmitter\Tests;

use PHPUnit\Framework\TestCase;
use Solo\HttpEmitter\Emitter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class EmitterTest extends TestCase
{
    private Emitter $emitter;

    protected function setUp(): void
    {
        $this->emitter = new Emitter();
    }

    public function testIsResponseEmptyWith204Status(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(204);

        $this->assertTrue($this->emitter->isResponseEmpty($response));
    }

    public function testIsResponseEmptyWith205Status(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(205);

        $this->assertTrue($this->emitter->isResponseEmpty($response));
    }

    public function testIsResponseEmptyWith304Status(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(304);

        $this->assertTrue($this->emitter->isResponseEmpty($response));
    }

    public function testIsResponseEmptyWithEmptyBody(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('read')->willReturn('');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $this->assertTrue($this->emitter->isResponseEmpty($response));
    }

    public function testIsResponseEmptyWithNonEmptyBody(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('read')->willReturn('test');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $this->assertFalse($this->emitter->isResponseEmpty($response));
    }

    public function testIsResponseEmptyWithNonSeekableStream(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->method('eof')->willReturn(true);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $this->assertTrue($this->emitter->isResponseEmpty($response));
    }

    public function testConstructorWithCustomChunkSize(): void
    {
        $emitter = new Emitter(8192);
        
        // Используем рефлексию для проверки приватного свойства
        $reflection = new \ReflectionClass($emitter);
        $property = $reflection->getProperty('responseChunkSize');
        $property->setAccessible(true);
        
        $this->assertEquals(8192, $property->getValue($emitter));
    }

    public function testConstructorWithDefaultChunkSize(): void
    {
        $emitter = new Emitter();
        
        $reflection = new \ReflectionClass($emitter);
        $property = $reflection->getProperty('responseChunkSize');
        $property->setAccessible(true);
        
        $this->assertEquals(4096, $property->getValue($emitter));
    }
} 