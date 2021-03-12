<?php

declare(strict_types=1);

/**
 * Copyright (c) 2017-2021 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/http-emitter
 */

namespace Narrowspark\HttpEmitter\Tests\Helper;

final class HeaderStack
{
    /**
     * Check if headers was sent.
     */
    public static bool $headersSent = false;

    public static ?string $headersFile = null;

    public static ?int $headersLine = null;

    /**
     * @psalm-var array<array-key, array<string, bool|int|string|null>>
     */
    private static array $data = [];

    /**
     * Reset state.
     */
    public static function reset(): void
    {
        self::$data = [];
    }

    /**
     * Push a header on the stack.
     *
     * @psalm-param array<string, bool|int|string|null> $header
     */
    public static function push(array $header): void
    {
        self::$data[] = $header;
    }

    /**
     * Return the current header stack.
     *
     * @psalm-return array<array-key, array<string, bool|int|string|null>>
     *
     * @return array<mixed, array<null|bool|int|string>>
     */
    public static function stack(): array
    {
        return self::$data;
    }

    /**
     * Verify if there's a header line on the stack.
     */
    public static function has(string $header): bool
    {
        foreach (self::$data as $item) {
            if ($item['header'] === $header) {
                return true;
            }
        }

        return false;
    }
}
