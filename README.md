# HTTP-Emitter

[![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)](https://github.com/solophp/http-emitter)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)](https://github.com/solophp/http-emitter)

PSR-7 HTTP Response Emitter for PHP 8+

## Installation

```bash
composer require solophp/http-emitter
```

## Usage

```php
use Solo\HttpEmitter\Emitter;
use Psr\Http\Message\ResponseInterface;

$emitter = new Emitter();
$emitter->emit($response);
```

## Features

- PSR-7 compliant HTTP response emitter
- Efficient chunked output for large responses
- Proper handling of connection status
- Support for all HTTP status codes
- Optimized for PHP 8+

## Requirements

- PHP ^8.0
- PSR HTTP Message ^1.1|^2.0

## Testing

```bash
# Run tests
composer test

# Code style check
composer cs

# Code style fix
composer cs-fix
```

## License

MIT
