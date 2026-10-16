# HTTP-Emitter

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/http-emitter.svg)](https://packagist.org/packages/solophp/http-emitter)
[![Tests](https://github.com/SoloPHP/HTTP-Emitter/actions/workflows/tests.yml/badge.svg)](https://github.com/SoloPHP/HTTP-Emitter/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)](https://github.com/solophp/http-emitter)

PSR-7 HTTP Response Emitter for PHP 8.1+. Implements `Solo\Contracts\Http\EmitterInterface`, so it plugs straight into [solophp/application](https://github.com/solophp/application).

## Installation

```bash
composer require solophp/http-emitter
```

## Usage

```php
use Solo\HttpEmitter\Emitter;

$emitter = new Emitter();
$emitter->emit($response);
```

The body is streamed in chunks of 4096 bytes. Pass another size to the constructor if you need to:

```php
$emitter = new Emitter(responseChunkSize: 65536);
```

### With solophp/application

Register the emitter in a service provider:

```php
use Solo\Contracts\Http\EmitterInterface;
use Solo\HttpEmitter\Emitter;

$container->set(EmitterInterface::class, fn() => new Emitter());
```

`Application::run()` resolves `EmitterInterface` from the container and emits the response returned by the middleware pipeline.

## Behavior

- The status line is sent after the headers, so a `Location` header can't reset the status code to `302`
- Repeated header values are sent as separate headers; `Set-Cookie` never replaces cookies that were already set
- Seekable bodies are rewound before output
- A valid `Content-Length` header limits how many body bytes are sent; otherwise the body is read to the end
- No body is sent for `1xx`, `204`, `205` and `304` responses
- Output stops early if the client disconnects
- If headers were already sent, only the body is emitted

## Production notes

- Keep `display_errors` off. Any output before `emit()` (a warning, a BOM or blank line before `<?php`, a stray `echo`) makes PHP send its default headers early, and the emitter can then only send the body
- `emit()` runs after the middleware pipeline, so error-handling middleware doesn't see failures while the body is read. By then the headers are out; log such errors with a global exception handler
- HEAD requests need no special handling: PHP drops the body of a HEAD response itself, including routes that fall back from HEAD to GET
- Bodies are sent in chunks, so memory use stays flat for large responses, unless `emit()` runs inside an `ob_start()` without a chunk size

## Requirements

- PHP ^8.1
- PSR HTTP Message ^1.1|^2.0
- solophp/contracts ^1.1

## Testing

```bash
# Code style, static analysis and tests
composer check

# Run tests
composer test

# Static analysis
composer stan

# Code style check
composer cs

# Code style fix
composer cs-fix
```

## License

MIT
