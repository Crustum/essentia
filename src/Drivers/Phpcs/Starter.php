<?php
declare(strict_types=1);

namespace Crustum\Essentia\Drivers\Phpcs;

use Crustum\Essentia\Drivers\Starter as BaseStarter;
use Crustum\Essentia\UserFilters\CaptureFilter;

/**
 * PHPCS driver starter.
 *
 * Forces JSON report output so agents receive structured diagnostics.
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
        return 'phpcs';
    }

    /**
     * Prepare PHPCS for structured JSON report parsing.
     *
     * @return void
     */
    public function start(): void
    {
        $this->registerNullFilter();
        $this->silenceStderr();

        /** @var array<int, string> $argv */
        $argv = $_SERVER['argv'] ?? [];
        $argv = $this->ensureJsonReport($argv);
        $_SERVER['argv'] = $argv;
        $GLOBALS['argv'] = $argv;

        $this->silenceStdout();
        $this->captureBufferedOutput();
    }

    /**
     * Capture PHPCS echo output that bypasses stdout stream filters on Windows.
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
     * Parse captured PHPCS JSON output into Essentia result shape.
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

        if ($captured === '') {
            return null;
        }

        $start = strpos($captured, '{');
        if ($start !== false && $start > 0) {
            $captured = substr($captured, $start);
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($captured, associative: true);

        if (!is_array($data)) {
            return [
                'result' => 'failed',
                'raw' => [$captured],
            ];
        }

        /** @var array{errors?: int, warnings?: int}|null $totals */
        $totals = is_array($data['totals'] ?? null) ? $data['totals'] : null;

        $errors = is_int($totals['errors'] ?? null) ? $totals['errors'] : 0;
        $warnings = is_int($totals['warnings'] ?? null) ? $totals['warnings'] : 0;

        $result = [
            'result' => $errors + $warnings > 0 ? 'failed' : 'passed',
            'errors' => $errors,
            'warnings' => $warnings,
        ];

        if ($errors + $warnings === 0) {
            return $result;
        }

        $filesWithIssues = $this->filesWithIssues($data);

        if ($filesWithIssues !== []) {
            $result['files'] = $filesWithIssues;
        }

        return $result;
    }

    /**
     * Keep only PHPCS files that contain errors or warnings.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filesWithIssues(array $data): array
    {
        if (!is_array($data['files'] ?? null)) {
            return [];
        }

        $files = [];

        foreach ($data['files'] as $path => $file) {
            if (!is_string($path) || !is_array($file)) {
                continue;
            }

            $fileErrors = is_int($file['errors'] ?? null) ? $file['errors'] : 0;
            $fileWarnings = is_int($file['warnings'] ?? null) ? $file['warnings'] : 0;
            $messages = is_array($file['messages'] ?? null) ? $file['messages'] : [];

            if ($fileErrors + $fileWarnings === 0 && $messages === []) {
                continue;
            }

            $files[$path] = $file;
        }

        return $files;
    }

    /**
     * Ensure PHPCS emits a JSON report.
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

        $filtered[] = '--report=json';

        return $filtered;
    }
}
