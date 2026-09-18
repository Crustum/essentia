<?php
declare(strict_types=1);

namespace Crustum\Essentia\Drivers\Structarmed;

use Crustum\Essentia\Drivers\Starter as BaseStarter;
use Crustum\Essentia\OutputCleaner;
use Crustum\Essentia\UserFilters\CaptureFilter;
use Crustum\Essentia\UserFilters\StderrCaptureFilter;

/**
 * StructArmed driver starter.
 *
 * Forces JSON report output so agents receive structured architecture
 * violations from `structarmed analyse`.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class Starter extends BaseStarter
{
    private static bool $outputBufferActive = false;

    /**
     * Get the tool name for this driver.
     *
     * @return string
     */
    public function name(): string
    {
        return 'structarmed';
    }

    /**
     * Prepare StructArmed for JSON report output and capture stdout.
     *
     * @return void
     */
    public function start(): void
    {
        /** @var array<int, string> $argv */
        $argv = $_SERVER['argv'];

        if (!$this->shouldTransform($argv)) {
            return;
        }

        $this->captureStderr();

        $argv = $this->ensureJsonReport($argv);
        $argv = $this->ensureNoProgress($argv);
        $_SERVER['argv'] = $argv;
        $GLOBALS['argv'] = $argv;

        $this->silenceStdout();
        $this->captureBufferedOutput();
    }

    /**
     * Capture StructArmed echo output that bypasses stdout stream filters on Windows.
     *
     * @return void
     */
    private function captureBufferedOutput(): void
    {
        ob_start(static function (string $buffer): string {
            CaptureFilter::append($buffer);

            return '';
        });

        self::$outputBufferActive = true;
    }

    /**
     * Determine whether the invocation is an analyse command.
     *
     * @param array<int, string> $argv
     * @return bool
     */
    private function shouldTransform(array $argv): bool
    {
        $command = $this->commandName($argv);

        return $command !== null && in_array($command, ['analyse', 'analyze'], true);
    }

    /**
     * Parse captured StructArmed JSON output into Essentia result shape.
     *
     * @return array<string, mixed>|null
     */
    public function parse(): ?array
    {
        if (self::$outputBufferActive && ob_get_level() > 0) {
            ob_end_clean();
            self::$outputBufferActive = false;
        }

        $captured = trim(CaptureFilter::output());

        CaptureFilter::reset();

        $stderr = trim(StderrCaptureFilter::output());

        StderrCaptureFilter::reset();

        if ($captured === '') {
            return $this->fallback($stderr);
        }

        $start = strpos($captured, '{');

        if ($start !== false && $start > 0) {
            $captured = substr($captured, $start);
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($captured, associative: true);

        if (!is_array($data) || !array_key_exists('violations', $data)) {
            return $this->fallback($stderr, $captured);
        }

        /** @var list<array<string, mixed>> $violations */
        $violations = is_array($data['violations'] ?? null) ? array_values($data['violations']) : [];
        $total = is_int($data['total'] ?? null) ? $data['total'] : count($violations);
        $passed = ($data['passed'] ?? false) === true;

        $result = [
            'result' => $passed ? 'passed' : 'failed',
            'total' => $total,
            'violations' => $violations,
        ];

        if (is_float($data['elapsed'] ?? null)) {
            $result['elapsed'] = $data['elapsed'];
        }

        return $result;
    }

    /**
     * Surface raw output lines from stderr and stdout.
     *
     * @return array<string, mixed>|null
     */
    private function fallback(string $stderr, string $stdout = ''): ?array
    {
        $lines = [];

        foreach ([$stderr, $stdout] as $output) {
            foreach (explode("\n", OutputCleaner::clean($output)) as $line) {
                $line = trim($line);

                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        if ($lines === []) {
            return null;
        }

        return [
            'raw' => $lines,
        ];
    }

    /**
     * Ensure StructArmed emits a JSON report.
     *
     * @param array<int, string> $argv
     * @return array<int, string>
     */
    private function ensureJsonReport(array $argv): array
    {
        $filtered = [];
        $skipNext = false;

        foreach ($argv as $arg) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if (str_starts_with($arg, '--report=')) {
                continue;
            }

            if ($arg === '--report') {
                $skipNext = true;

                continue;
            }

            $filtered[] = $arg;
        }

        return $this->addOption($filtered, '--report=json');
    }

    /**
     * Ensure progress output is disabled.
     *
     * @param array<int, string> $argv
     * @return array<int, string>
     */
    private function ensureNoProgress(array $argv): array
    {
        if (in_array('--no-progress', $argv, true)) {
            return $argv;
        }

        return $this->addOption($argv, '--no-progress');
    }
}
