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

use const SEEK_SET;
use function is_callable;
use function Safe\substr;
use function strlen;

final class StreamMock
{
    /**
     * @param callable|string $contents
     * @param null|callable   $trackPeakBufferLength
     *
     * @noRector
     */
    public function __construct(
        private $contents,
        private int $size,
        private int $position,
        private $trackPeakBufferLength = null
    ) {
    }

    public function handleToString(): string
    {
        $this->position = $this->size;

        return is_callable($this->contents) ? ($this->contents)(0) : $this->contents;
    }

    public function handleTell(): int
    {
        return $this->position;
    }

    public function handleEof(): bool
    {
        return $this->position >= $this->size;
    }

    /**
     * @noRector \Rector\DeadCode\Rector\ClassMethod\RemoveUnusedParameterRector
     */
    public function handleSeek(int $offset, ?int $whence = SEEK_SET): bool
    {
        if ($offset >= $this->size) {
            return false;
        }

        $this->position = $offset;

        return true;
    }

    public function handleRewind(): bool
    {
        $this->position = 0;

        return true;
    }

    public function handleRead(int $length): string
    {
        if ($this->trackPeakBufferLength !== null) {
            ($this->trackPeakBufferLength)($length);
        }

        $data = is_callable($this->contents)
            ? ($this->contents)($this->position, $length)
            : substr($this->contents, $this->position, $length);

        $this->position += strlen($data);

        return $data;
    }

    public function handleGetContents(): string
    {
        $remainingContents = is_callable($this->contents)
            ? ($this->contents)($this->position)
            : substr($this->contents, $this->position);

        $this->position += strlen($remainingContents);

        return $remainingContents;
    }
}
