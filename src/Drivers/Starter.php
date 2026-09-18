<?php
declare(strict_types=1);

namespace Crustum\Essentia\Drivers;

use Crustum\Essentia\Contracts\Driver;
use Crustum\Essentia\Execution;
use Crustum\Essentia\UserFilters\CaptureFilter;
use Crustum\Essentia\UserFilters\CleanFilter;
use Crustum\Essentia\UserFilters\NullFilter;
use Crustum\Essentia\UserFilters\StderrCaptureFilter;

/**
 * Base driver starter utilities.
 *
 * @internal
 * @codeCoverageIgnore
 */
abstract class Starter implements Driver
{
    /**
     * @var array<int, true>
     */
    private static array $cleanedStreams = [];

    /**
     * @var array<int, true>
     */
    private static array $capturedStreams = [];

    /**
     * Register a stdout/stderr nulling filter once.
     *
     * @return void
     */
    protected function registerNullFilter(): void
    {
        if (!in_array('agent_output_null', stream_get_filters(), true)) {
            stream_filter_register('agent_output_null', NullFilter::class);
        }
    }

    /**
     * Attach stdout capture filter to suppress noisy output.
     *
     * @return void
     */
    protected function silenceStdout(): void
    {
        if (!in_array('agent_output_capture', stream_get_filters(), true)) {
            stream_filter_register('agent_output_capture', CaptureFilter::class);
        }

        CaptureFilter::reset();

        $execution = Execution::current();

        foreach ($this->writableStdoutStreams() as $stream) {
            if (!is_resource($stream)) {
                continue;
            }

            $streamId = get_resource_id($stream);

            if (isset(self::$capturedStreams[$streamId])) {
                continue;
            }

            $filter = stream_filter_append($stream, 'agent_output_capture', STREAM_FILTER_WRITE) ?: null;
            self::$capturedStreams[$streamId] = true;

            if ($execution->filter === null && is_resource($filter)) {
                $execution->filter = $filter;
            }
        }
    }

    /**
     * @return list<resource>
     */
    private function writableStdoutStreams(): array
    {
        $streams = [STDOUT];

        $stdout = fopen('php://stdout', 'wb');

        if (is_resource($stdout)) {
            $streams[] = $stdout;
        }

        return $streams;
    }

    /**
     * Attach the null filter to stderr.
     *
     * @return void
     */
    protected function silenceStderr(): void
    {
        stream_filter_append(STDERR, 'agent_output_null', STREAM_FILTER_WRITE);
    }

    /**
     * Attach the stderr capture filter to collect stderr output.
     *
     * @return void
     */
    protected function captureStderr(): void
    {
        if (!in_array('agent_output_stderr_capture', stream_get_filters(), true)) {
            stream_filter_register('agent_output_stderr_capture', StderrCaptureFilter::class);
        }

        StderrCaptureFilter::reset();

        stream_filter_append(STDERR, 'agent_output_stderr_capture', STREAM_FILTER_WRITE);
    }

    /**
     * Save the current stdout stream handle for later restore.
     *
     * @return void
     */
    protected function saveStdout(): void
    {
        $execution = Execution::current();

        $execution->stdout = fopen('php://stdout', 'w') ?: STDOUT;
    }

    /**
     * Get the first non-option argument, treated as the command name.
     *
     * @param array<int, string> $argv
     * @return string|null
     */
    protected function commandName(array $argv): ?string
    {
        foreach (array_slice($argv, 1) as $arg) {
            if (!str_starts_with($arg, '-')) {
                return $arg;
            }
        }

        return null;
    }

    /**
     * Append an option, keeping it before the end-of-options separator.
     *
     * @param array<int, string> $argv
     * @return array<int, string>
     */
    protected function addOption(array $argv, string $option): array
    {
        $separator = array_search('--', $argv, true);

        if (!is_int($separator)) {
            $argv[] = $option;

            return $argv;
        }

        array_splice($argv, $separator, 0, [$option]);

        return $argv;
    }

    /**
     * Register the pass-through output cleaning filter once.
     *
     * @return void
     */
    protected function registerCleanFilter(): void
    {
        if (!in_array('agent_output_clean', stream_get_filters(), true)) {
            stream_filter_register('agent_output_clean', CleanFilter::class);
        }
    }

    /**
     * Attach the cleaning filter to a writable stream.
     *
     * @param resource $stream
     * @return void
     */
    protected function cleanStream(mixed $stream): void
    {
        if (!is_resource($stream)) {
            return;
        }

        $streamId = get_resource_id($stream);

        if (isset(self::$cleanedStreams[$streamId])) {
            return;
        }

        $this->registerCleanFilter();
        stream_filter_append($stream, 'agent_output_clean', STREAM_FILTER_WRITE);
        self::$cleanedStreams[$streamId] = true;
    }

    /**
     * Attach cleaning filters to CLI stdout and stderr handles.
     *
     * Cake opens php://stdout separately from the STDOUT constant, so both are wired.
     *
     * @return void
     */
    protected function cleanConsoleStreams(): void
    {
        foreach ([STDOUT, STDERR] as $stream) {
            $this->cleanStream($stream);
        }

        foreach (['php://stdout', 'php://stderr'] as $uri) {
            $handle = fopen($uri, 'wb');

            if (is_resource($handle)) {
                $this->cleanStream($handle);
            }
        }
    }
}
