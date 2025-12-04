<?php

declare(strict_types=1);

namespace Solo\HttpEmitter;

class EmitterTestState
{
    public static bool $headersSent = false;
    public static int $connectionStatus = CONNECTION_NORMAL;
    /** @var array<int, array{header: string, replace: bool, code: int}> */
    public static array $headers = [];

    public static function reset(): void
    {
        self::$headersSent = false;
        self::$connectionStatus = CONNECTION_NORMAL;
        self::$headers = [];
    }
}

function headers_sent(): bool
{
    return EmitterTestState::$headersSent;
}

function header(string $header, bool $replace = true, int $responseCode = 0): void
{
    EmitterTestState::$headers[] = [
        'header' => $header,
        'replace' => $replace,
        'code' => $responseCode,
    ];
}

function connection_status(): int
{
    return EmitterTestState::$connectionStatus;
}
