<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter\Tests\Helper;

class HeaderStack
{
    /**
     * @var array
     */
    private static $data = [];

    /**
     * Reset state.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$data = [];
    }

    /**
     * Push a header on the stack.
     *
     * @param string $header
     */
    public static function push(string $header)
    {
        self::$data[] = $header;
    }

    /**
     * Return the current header stack.
     *
     * @return array
     */
    public static function stack(): array
    {
        return self::$data;
    }
}
