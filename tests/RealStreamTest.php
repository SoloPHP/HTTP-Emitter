<?php declare(strict_types=1);

namespace Solo\HttpEmitter\Tests;

use PHPUnit\Framework\TestCase;
use Solo\HttpEmitter\Emitter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class RealStreamTest extends TestCase
{
    private Emitter $emitter;

    protected function setUp(): void
    {
        $this->emitter = new Emitter();
    }

    public function testEmitResponseWithRealStream(): void
    {
        // Создаем реальный stream
        $stream = $this->createRealStream('test content');
        
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaders')->willReturn([
            'Content-Type' => ['text/plain']
        ]);
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getReasonPhrase')->willReturn('OK');
        $response->method('getHeaderLine')->willReturn('12');
        $response->method('getBody')->willReturn($stream);

        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();

        $this->assertEquals('test content', $output);
    }

    public function testEmitResponseWithLargeContent(): void
    {
        $largeContent = str_repeat('a', 1000);
        $stream = $this->createRealStream($largeContent);
        
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getReasonPhrase')->willReturn('OK');
        $response->method('getHeaderLine')->willReturn('1000');
        $response->method('getBody')->willReturn($stream);

        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();

        $this->assertEquals($largeContent, $output);
    }

    public function testEmitEmptyResponse(): void
    {
        $stream = $this->createRealStream('');
        
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getReasonPhrase')->willReturn('OK');
        $response->method('getHeaderLine')->willReturn('0');
        $response->method('getBody')->willReturn($stream);

        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();

        $this->assertEquals('', $output);
    }

    private function createRealStream(string $content): StreamInterface
    {
        return new class($content) implements StreamInterface {
            private string $content;
            private int $position = 0;
            private bool $closed = false;

            public function __construct(string $content)
            {
                $this->content = $content;
            }

            public function __toString(): string
            {
                return $this->content;
            }

            public function close(): void
            {
                $this->closed = true;
            }

            public function detach()
            {
                $this->closed = true;
                return null;
            }

            public function getSize(): ?int
            {
                return strlen($this->content);
            }

            public function tell(): int
            {
                return $this->position;
            }

            public function eof(): bool
            {
                return $this->position >= strlen($this->content);
            }

            public function isSeekable(): bool
            {
                return true;
            }

            public function seek(int $offset, int $whence = SEEK_SET): void
            {
                switch ($whence) {
                    case SEEK_SET:
                        $this->position = $offset;
                        break;
                    case SEEK_CUR:
                        $this->position += $offset;
                        break;
                    case SEEK_END:
                        $this->position = strlen($this->content) + $offset;
                        break;
                }
            }

            public function rewind(): void
            {
                $this->position = 0;
            }

            public function isWritable(): bool
            {
                return false;
            }

            public function write(string $string): int
            {
                throw new \RuntimeException('Stream is not writable');
            }

            public function isReadable(): bool
            {
                return true;
            }

            public function read(int $length): string
            {
                if ($this->eof()) {
                    return '';
                }
                
                $data = substr($this->content, $this->position, $length);
                $this->position += strlen($data);
                return $data;
            }

            public function getContents(): string
            {
                $contents = substr($this->content, $this->position);
                $this->position = strlen($this->content);
                return $contents;
            }

            public function getMetadata(?string $key = null)
            {
                return null;
            }
        };
    }
} 