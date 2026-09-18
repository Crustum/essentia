<?php
declare(strict_types=1);

namespace Crustum\Essentia\Drivers\Phpstan;

use Crustum\Essentia\Drivers\Starter as BaseStarter;
use Crustum\Essentia\OutputCleaner;
use Crustum\Essentia\UserFilters\CaptureFilter;
use Crustum\Essentia\UserFilters\StderrCaptureFilter;

/**
 * PHPStan driver starter.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class Starter extends BaseStarter
{
    /**
     * Get the tool name for this driver.
     *
     * @return string
     */
    public function name(): string
    {
        return 'phpstan';
    }

    /**
     * Prepare PHPStan for JSON error output and capture stdout.
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

        $argv = $this->ensureErrorFormatJson($argv);
        $argv = $this->ensureNoProgress($argv);
        $_SERVER['argv'] = $argv;

        $this->silenceStdout();
    }

    /**
     * Determine whether the invocation should produce a JSON report.
     *
     * @param array<int, string> $argv
     * @return bool
     */
    private function shouldTransform(array $argv): bool
    {
        $arguments = $this->analyseArguments($argv);

        return $arguments !== null && $this->producesJsonReport($arguments);
    }

    /**
     * Extract arguments belonging to the analyse command.
     *
     * @param array<int, string> $argv
     * @return array<int, string>|null
     */
    private function analyseArguments(array $argv): ?array
    {
        $command = $this->commandName($argv);

        if ($command !== null && !in_array($command, ['analyse', 'analyze'], true)) {
            return null;
        }

        $arguments = [];
        $skipped = false;

        foreach (array_slice($argv, 1) as $arg) {
            if (!$skipped && $arg === $command) {
                $skipped = true;

                continue;
            }

            $arguments[] = $arg;
        }

        return $arguments;
    }

    /**
     * Determine whether the arguments produce a JSON report.
     *
     * @param array<int, string> $arguments
     * @return bool
     */
    private function producesJsonReport(array $arguments): bool
    {
        foreach ($arguments as $arg) {
            if (in_array($arg, ['--fix', '--watch', '--pro'], true)) {
                return false;
            }

            if (str_starts_with($arg, '--generate-baseline') || str_starts_with($arg, '-b')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse captured PHPStan JSON output into Essentia result shape.
     *
     * @return array<string, mixed>|null
     */
    public function parse(): ?array
    {
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

        if (!is_array($data) || !isset($data['totals'])) {
            return $this->fallback($stderr, $captured);
        }

        /** @var array<string, list<array{line: int, message: string, identifier: string, ignorable?: bool, tip?: string}>> $errorDetails */
        $errorDetails = [];
        $totalFileErrors = 0;

        /** @var array<string, array{errors: int, messages: list<array{message: string, line: int, identifier?: string, ignorable?: bool, tip?: string}>}> $files */
        $files = is_array($data['files'] ?? null) ? $data['files'] : [];

        foreach ($files as $file => $fileData) {
            foreach ($fileData['messages'] as $message) {
                $totalFileErrors++;

                $detail = [
                    'line' => $message['line'],
                    'message' => $message['message'],
                    'identifier' => $message['identifier'] ?? 'unknown',
                ];

                if (isset($message['ignorable']) && $message['ignorable'] === false) {
                    $detail['ignorable'] = false;
                }

                if (isset($message['tip']) && $message['tip'] !== '') {
                    $detail['tip'] = $message['tip'];
                }

                $errorDetails[$file][] = $detail;
            }
        }

        /** @var list<string> $errors */
        $errors = is_array($data['errors'] ?? null) ? $data['errors'] : [];

        /** @var list<string> $generalErrors */
        $generalErrors = array_values(array_filter($errors, static fn(string $error): bool => $error !== ''));

        $totalErrors = $totalFileErrors + count($generalErrors);

        /** @var array<string, mixed> $result */
        $result = [
            'result' => $totalErrors > 0 ? 'failed' : 'passed',
            'errors' => $totalErrors,
        ];

        if ($errorDetails !== []) {
            $verbose = $this->isVerbose();
            $limit = 30;

            if (!$verbose && $totalFileErrors > $limit) {
                $result['error_details'] = $this->truncateGrouped($errorDetails, $limit);
                $result['truncated'] = true;
                $result['hint'] = 'Pass -v to see all errors.';
            } else {
                $result['error_details'] = $errorDetails;
            }

            $result['instructions'] = $this->agentInstructions();
        }

        if ($generalErrors !== []) {
            $result['general_errors'] = $generalErrors;
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
     * Provide agent guidance for fixing PHPStan errors.
     *
     * @return string
     */
    private function agentInstructions(): string
    {
        return <<<'TXT'
            Each error has an associated identifier, like `argument.type` or `return.missing`. Each error identifier has documentation at URL https://phpstan.org/error-identifiers/<identifier>, which contains a code example, explanation why it is an error, and instructions on how to fix it. Before fixing the error, fetch the documentation page for its identifier. The error usually indicates a real bug or incorrect type in the code. Fix the underlying cause, do not just make the error go away. Do not add `@phpstan-ignore` comments, `@phpstan-ignore-next-line` comments, or baseline entries to suppress the error. Do not use assert() or inline @var PHPDoc tag to override PHPStan's inferred type. Do not add type casts just to silence errors. Do not widen parameter or return types just to make the error go away.
            TXT;
    }

    /**
     * Truncate grouped errors to a fixed limit.
     *
     * @param array<string, list<array{line: int, message: string, identifier: string, ignorable?: bool, tip?: string}>> $grouped
     * @return array<string, list<array{line: int, message: string, identifier: string, ignorable?: bool, tip?: string}>>
     */
    private function truncateGrouped(array $grouped, int $limit): array
    {
        $result = [];
        $count = 0;

        foreach ($grouped as $file => $errors) {
            foreach ($errors as $error) {
                if ($count >= $limit) {
                    break 2;
                }

                $result[$file][] = $error;
                $count++;
            }
        }

        return $result;
    }

    /**
     * Check whether PHPStan was invoked in verbose mode.
     *
     * @return bool
     */
    private function isVerbose(): bool
    {
        /** @var array<int, string> $argv */
        $argv = $_SERVER['argv'] ?? [];

        foreach ($argv as $arg) {
            if (in_array($arg, ['-v', '-vv', '-vvv', '--verbose'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure PHPStan uses JSON error format.
     *
     * @param array<int, string> $argv
     * @return array<int, string>
     */
    private function ensureErrorFormatJson(array $argv): array
    {
        $filtered = [];
        $skipNext = false;

        foreach ($argv as $arg) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if (str_starts_with($arg, '--error-format=')) {
                continue;
            }

            if ($arg === '--error-format') {
                $skipNext = true;

                continue;
            }

            $filtered[] = $arg;
        }

        return $this->addOption($filtered, '--error-format=json');
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
