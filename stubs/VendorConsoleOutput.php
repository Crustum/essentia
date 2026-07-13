<?php
declare(strict_types=1);

namespace Cake\Console;

class VendorConsoleOutput
{
    /**
     * @param resource|string $stream
     */
    public function __construct($stream = 'php://stdout')
    {
    }

    /**
     * @param array<string>|string $message
     */
    public function write(array|string $message, int $newlines = 1): int
    {
        return 0;
    }

    protected function _write(string $message): int
    {
        return 0;
    }
}
