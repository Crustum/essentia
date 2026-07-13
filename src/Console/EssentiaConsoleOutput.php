<?php
declare(strict_types=1);

namespace Crustum\Essentia\Console;

use Cake\Console\VendorConsoleOutput;
use Crustum\Essentia\OutputCleaner;

/**
 * Cake console output that strips agent-noisy formatting on write.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class EssentiaConsoleOutput extends VendorConsoleOutput
{
    /**
     * Write cleaned output to the underlying stream.
     *
     * @param string $message Message to write.
     * @return int
     */
    protected function _write(string $message): int
    {
        return parent::_write(OutputCleaner::clean($message));
    }
}
