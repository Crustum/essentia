<?php
declare(strict_types=1);

namespace Crustum\Essentia\Drivers\Concerns;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Test\Finished;

/**
 * Collects the slowest tests when profiling is enabled.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class ProfileCollector
{
    private static bool $executionStarted = false;

    private static ?HRTime $startTime = null;

    private static float $preparedAt = 0.0;

    /**
     * @var list<array{test: string, file: string, duration_ms: int}>
     */
    private static array $entries = [];

    /**
     * Mark that a test execution has started.
     *
     * @return void
     */
    public static function executionStarted(): void
    {
        self::$executionStarted = true;
    }

    /**
     * Check whether execution has started (including parallel runs).
     *
     * @return bool
     */
    public static function hasExecutionStarted(): bool
    {
        return self::$executionStarted;
    }

    /**
     * Start the execution timer from a PHPUnit telemetry timestamp.
     *
     * @return void
     */
    public static function startTimer(HRTime $time): void
    {
        self::$startTime = $time;
    }

    /**
     * Start the execution timer from nanoseconds.
     *
     * @return void
     */
    public static function startTimerFromNanoseconds(float $nanoseconds): void
    {
        $seconds = (int)($nanoseconds / 1_000_000_000);
        $nanos = (int)($nanoseconds - ($seconds * 1_000_000_000));

        self::$startTime = HRTime::fromSecondsAndNanoseconds($seconds, $nanos);
    }

    /**
     * Get the duration since start in milliseconds.
     *
     * @return int
     */
    public static function durationMs(): int
    {
        if (!self::$startTime instanceof HRTime) {
            return 0;
        }

        $startNs = (self::$startTime->seconds() * 1_000_000_000) + self::$startTime->nanoseconds();

        return (int)round((hrtime(true) - $startNs) / 1_000_000);
    }

    /**
     * Mark the moment a test was prepared (used for per-test duration).
     *
     * @return void
     */
    public static function prepared(): void
    {
        self::$preparedAt = hrtime(true);
    }

    /**
     * Record a finished test event and its duration.
     *
     * @return void
     */
    public static function finished(Finished $event): void
    {
        $test = $event->test();

        $file = $test->file();
        $doubleColonPos = strpos($file, '::');
        if ($doubleColonPos !== false) {
            $file = substr($file, 0, $doubleColonPos);
        }

        self::$entries[] = [
            'test' => $test instanceof TestMethod ? $test->nameWithClass() : $test->id(),
            'file' => $file,
            'duration_ms' => self::$preparedAt > 0
                ? (int)round((hrtime(true) - self::$preparedAt) / 1_000_000)
                : (int)round($event->telemetryInfo()->durationSincePrevious()->asFloat() * 1000),
        ];

        self::$preparedAt = 0.0;
    }

    /**
     * Get the collected profile entries.
     *
     * @return list<array{test: string, file: string, duration_ms: int}>
     */
    public static function entries(): array
    {
        return self::$entries;
    }
}
