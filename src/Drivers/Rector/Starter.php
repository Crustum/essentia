<?php
declare(strict_types=1);

namespace Crustum\Essentia\Drivers\Rector;

use Crustum\Essentia\Drivers\Starter as BaseStarter;

/**
 * Rector driver starter.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class Starter extends BaseStarter
{
    private ?int $outputBufferLevel = null;

    /**
     * Get the tool name for this driver.
     *
     * @return string
     */
    public function name(): string
    {
        return 'rector';
    }

    /**
     * Force Rector JSON output and start output buffering.
     *
     * @return void
     */
    public function start(): void
    {
        /** @var array<int, string> $argv */
        $argv = $_SERVER['argv'];
        $argv = $this->ensureOutputFormatJson($argv);
        $_SERVER['argv'] = $argv;
        $GLOBALS['argv'] = $argv;

        $this->outputBufferLevel = ob_get_level();
        ob_start();
    }

    /**
     * Parse buffered Rector JSON output into Essentia result shape.
     *
     * @return array<string, mixed>|null
     */
    public function parse(): ?array
    {
        $captured = trim($this->bufferedOutput());

        if ($captured === '') {
            return null;
        }

        $start = strpos($captured, '{');

        if ($start !== false && $start > 0) {
            $captured = substr($captured, $start);
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($captured, associative: true);

        if (!is_array($data) || !is_array($data['totals'] ?? null)) {
            return [
                'result' => 'failed',
                'raw' => [$captured],
            ];
        }

        $changedFiles = $data['totals']['changed_files'] ?? 0;
        $errors = $data['totals']['errors'] ?? 0;

        if (!is_int($changedFiles) || !is_int($errors)) {
            return [
                'result' => 'failed',
                'raw' => [$captured],
            ];
        }

        return [
            'result' => $errors > 0 || ($changedFiles > 0 && $this->isDryRun()) ? 'failed' : 'passed',
        ] + $data;
    }

    /**
     * Drain output buffers back to the saved level.
     *
     * @return string
     */
    private function bufferedOutput(): string
    {
        if ($this->outputBufferLevel === null) {
            return '';
        }

        $buffered = '';

        while (ob_get_level() > $this->outputBufferLevel) {
            $buffer = ob_get_clean();

            if ($buffer === false) {
                break;
            }

            $buffered = $buffer . $buffered;
        }

        return $buffered;
    }

    /**
     * Ensure Rector uses JSON output format.
     *
     * @param array<int, string> $argv
     * @return array<int, string>
     */
    private function ensureOutputFormatJson(array $argv): array
    {
        $filtered = [];
        $skipNext = false;

        foreach ($argv as $arg) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if (str_starts_with($arg, '--output-format=')) {
                continue;
            }

            if ($arg === '--output-format') {
                $skipNext = true;

                continue;
            }

            $filtered[] = $arg;
        }

        $filtered[] = '--output-format=json';

        return $filtered;
    }

    /**
     * Check whether Rector was invoked in dry-run mode.
     *
     * @return bool
     */
    private function isDryRun(): bool
    {
        /** @var array<int, string> $argv */
        $argv = $_SERVER['argv'] ?? [];

        foreach ($argv as $arg) {
            if (in_array($arg, ['--dry-run', '-n'], true)) {
                return true;
            }
        }

        return false;
    }
}
