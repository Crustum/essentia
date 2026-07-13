<?php
declare(strict_types=1);

namespace Crustum\Essentia;

use Crustum\Essentia\Contracts\Driver;
use Crustum\Essentia\Exceptions\ShouldNotHappenException;
use Crustum\Essentia\UserFilters\CaptureFilter;
use Laravel\AgentDetector\AgentResult;

/**
 * Tracks the currently running tool execution.
 *
 * @internal
 * @codeCoverageIgnore
 * @phpstan-type TestDetail array{test: string, file: string, line: int, message: string}
 * @phpstan-type ProfileEntry array{test: string, file: string, duration_ms: int}
 * @phpstan-type Result array{
 *   result: 'passed'|'failed',
 *   tests: int,
 *   passed: int,
 *   duration_ms: int,
 *   failed?: int,
 *   failures?: list<array{test: string, file: string, line: int, message: string}>,
 *   errors?: int,
 *   error_details?: list<array{test: string, file: string, line: int, message: string}>,
 *   skipped?: int,
 *   profile?: list<array{test: string, file: string, duration_ms: int}>,
 *   raw?: list<string>
 * }
 */
final class Execution
{
    private static ?self $instance = null;

    /**
     * Execution constructor.
     *
     * @param \Laravel\AgentDetector\AgentResult $agent
     * @param \Crustum\Essentia\Contracts\Driver $driver
     * @param resource|null $stdout
     * @param resource|null $filter
     */
    private function __construct(
        public readonly AgentResult $agent,
        public readonly Driver $driver,
        public mixed $stdout = null,
        public mixed $filter = null,
    ) {
    }

    /**
     * Start Essentia execution for the current CLI tool.
     *
     * Determines the active driver based on the invoked binary, and initializes
     * stdout capture/argument mutation for structured output.
     *
     * @param \Laravel\AgentDetector\AgentResult $agent Agent detection result.
     * @param array<int, string> $argv CLI argv.
     * @return void
     */
    public static function start(AgentResult $agent, array $argv): void
    {
        if (self::running()) {
            throw new ShouldNotHappenException();
        }

        $binary = basename($argv[0] ?? '');

        $starter = match ($binary) {
            'cake', 'cake.php', 'cake.bat' => new Drivers\Cake\Console\Starter(),
            'paratest' => new Drivers\Paratest\Starter(),
            'pest' => new Drivers\Pest\Starter(),
            'phpcs' => new Drivers\Phpcs\Starter(),
            'phpstan', 'phpstan.phar' => new Drivers\Phpstan\Starter(),
            'phpunit' => new Drivers\Phpunit\Starter(),
            'rector' => new Drivers\Rector\Starter(),
            default => self::resolveCakeConsoleStarter($argv),
        };

        if ($starter instanceof Driver) {
            self::$instance = new self(
                $agent,
                $starter,
            );

            $starter->start();
        }
    }

    /**
     * Detect Cake console invocations when argv[0] is php.exe or a wrapper script.
     *
     * @param array<int, string> $argv
     * @return \Crustum\Essentia\Drivers\Cake\Console\Starter|null
     */
    private static function resolveCakeConsoleStarter(array $argv): ?Drivers\Cake\Console\Starter
    {
        foreach ($argv as $argument) {
            $basename = basename($argument);

            if (in_array($basename, ['cake.php', 'cake', 'cake.bat'], true)) {
                return new Drivers\Cake\Console\Starter();
            }

            $realpath = realpath($argument);

            if ($realpath === false) {
                continue;
            }

            $normalized = str_replace('\\', '/', $realpath);

            if (str_ends_with($normalized, '/bin/cake.php')) {
                return new Drivers\Cake\Console\Starter();
            }
        }

        return null;
    }

    /**
     * Check if an execution is running.
     *
     * @return bool
     */
    public static function running(): bool
    {
        return self::$instance instanceof self;
    }

    /**
     * Get the current execution.
     *
     * @return self
     */
    public static function current(): self
    {
        return self::$instance ?? throw new ShouldNotHappenException();
    }

    /**
     * Restore the stdout.
     *
     * @return void
     */
    public function restoreStdout(): void
    {
        if (is_resource($this->filter)) {
            stream_filter_remove($this->filter);

            $this->filter = null;
        }
    }

    /**
     * Flush the stdout.
     *
     * @return void
     */
    public function flushStdout(): void
    {
        if (!is_resource($this->filter)) {
            return;
        }

        $captured = CaptureFilter::output();

        $this->restoreStdout();

        if ($captured !== '') {
            fwrite(STDOUT, $captured);
        }
    }
}
