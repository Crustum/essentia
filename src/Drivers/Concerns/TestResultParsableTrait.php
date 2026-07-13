<?php
declare(strict_types=1);

namespace Crustum\Essentia\Drivers\Concerns;

use Closure;
use Pest\Plugins\Parallel\Paratest\WrapperRunner;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Code\Throwable;
use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestRunner\ExecutionStartedSubscriber;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Configuration\Registry as ConfigurationRegistry;
use Throwable as PhpThrowable;

/**
 * Parses the PHPUnit test result facade into Essentia JSON result shape.
 *
 * @internal
 * @codeCoverageIgnore
 */
trait TestResultParsableTrait
{
    public ?TestResult $testResult = null;

    private bool $executionFinished = false;

    /**
     * Register a subscriber to mark the execution finished.
     *
     * @return void
     */
    protected function registerExecutionFinishedSubscriber(): void
    {
        try {
            $markFinished = function (): void {
                $this->executionFinished = true;
            };

            EventFacade::instance()->registerSubscriber(
                new readonly class ($markFinished) implements ExecutionFinishedSubscriber
                {
                    /**
                     * @param \Closure(): void $markFinished
                     */
                    public function __construct(private Closure $markFinished)
                    {
                    }

                    /**
                     * @return void
                     */
                    public function notify(ExecutionFinished $event): void
                    {
                        ($this->markFinished)();
                    }
                },
            );
        } catch (PhpThrowable) {
        }
    }

    /**
     * Start a timer based on PHPUnit telemetry events.
     *
     * @return void
     */
    protected function startTimer(): void
    {
        try {
            EventFacade::instance()->registerSubscriber(
                new class implements ExecutionStartedSubscriber
                {
                    /**
                     * @return void
                     */
                    public function notify(ExecutionStarted $event): void
                    {
                        ProfileCollector::executionStarted();
                        ProfileCollector::startTimer($event->telemetryInfo()->time());
                    }
                },
            );
        } catch (PhpThrowable) {
        }
    }

    /**
     * Register subscribers to collect per-test profiling info.
     *
     * @return void
     */
    protected function registerProfileSubscriber(): void
    {
        /** @var list<string> $argv */
        $argv = $_SERVER['argv'] ?? [];

        if (!in_array('--profile', $argv, true)) {
            return;
        }

        EventFacade::instance()->registerSubscribers(
            new class implements PreparedSubscriber
            {
                /**
                 * @return void
                 */
                public function notify(Prepared $event): void
                {
                    ProfileCollector::prepared();
                }
            },
            new class implements FinishedSubscriber
            {
                /**
                 * @return void
                 */
                public function notify(Finished $event): void
                {
                    ProfileCollector::finished($event);
                }
            },
        );
    }

    /**
     * Parse the current test result facade into a structured JSON payload.
     *
     * @return array<string, mixed>|null
     */
    public function parse(): ?array
    {
        $testResult = $this->resolveTestResult();

        if (!$testResult instanceof TestResult) {
            return null;
        }

        if ($testResult->numberOfTestsRun() > 0 || ProfileCollector::hasExecutionStarted()) {
            return $this->parseTestResult($testResult);
        }

        return null;
    }

    /**
     * Resolve the active PHPUnit test result facade.
     *
     * @return \PHPUnit\TestRunner\TestResult\TestResult|null
     */
    private function resolveTestResult(): ?TestResult
    {
        if ($this->testResult instanceof TestResult) {
            return $this->testResult;
        }

        if (class_exists(WrapperRunner::class, false) && WrapperRunner::$result instanceof TestResult) {
            return WrapperRunner::$result;
        }

        if (!$this->executionFinished) {
            return null;
        }

        try {
            return TestResultFacade::result();
        } catch (PhpThrowable) {
            return null;
        }
    }

    /**
     * Convert a PHPUnit TestResult into a Essentia result array.
     *
     * @return array<string, mixed>
     */
    private function parseTestResult(TestResult $testResult): array
    {
        $failedCount = $testResult->numberOfTestFailedEvents();
        $erroredCount = $testResult->numberOfTestErroredEvents();
        $skipped = $testResult->numberOfTestSkippedEvents() + $testResult->numberOfTestSkippedByTestSuiteSkippedEvents();
        $incomplete = $testResult->numberOfTestMarkedIncompleteEvents();
        $tests = $testResult->numberOfTestsRun();
        $assertions = $testResult->numberOfAssertions();
        $deprecations = $testResult->numberOfPhpOrUserDeprecations();
        $warnings = $testResult->numberOfWarnings();
        $notices = $testResult->numberOfNotices();
        $risky = $testResult->numberOfTestsWithTestConsideredRiskyEvents();
        $ignoredByBaseline = $testResult->numberOfIssuesIgnoredByBaseline();
        $hasNoTests = $tests === 0;
        $noTestsFoundAndFailsOnEmpty = $hasNoTests && $this->failsOnEmptyTestSuite();

        $durationMs = ProfileCollector::durationMs();

        /** @var list<array{test: string, file: string, line: int, message: string}> $failureDetails */
        $failureDetails = [];

        foreach ($testResult->testFailedEvents() as $event) {
            $throwable = $event->throwable();
            $message = trim($throwable->description());

            if ($event instanceof Failed) {
                $test = $event->test();
                $file = $test->file();
                $line = $test instanceof TestMethod ? $test->line() : 0;

                [$file, $line] = $this->resolveTestLocation($file, $line, $throwable);

                $failureDetails[] = [
                    'test' => $test instanceof TestMethod ? $test->nameWithClass() : $test->id(),
                    'file' => $file,
                    'line' => $line,
                    'message' => $message,
                ];

                continue;
            }

            [$file, $line] = $this->resolveTestLocation('', 0, $throwable);

            $failureDetails[] = [
                'test' => $event->testClassName() . '::' . $event->calledMethod()->methodName(),
                'file' => $file,
                'line' => $line,
                'message' => $message,
            ];
        }

        /** @var list<array{test: string, file: string, line: int, message: string}> $errorDetails */
        $errorDetails = [];

        foreach ($testResult->testErroredEvents() as $event) {
            if ($event instanceof Errored) {
                $test = $event->test();
                $throwable = $event->throwable();
                $message = trim($throwable->message());
                $file = $test->file();
                $line = $test instanceof TestMethod ? $test->line() : 0;

                [$file, $line] = $this->resolveTestLocation($file, $line, $throwable);

                $errorDetails[] = [
                    'test' => $test instanceof TestMethod ? $test->nameWithClass() : $test->id(),
                    'file' => $file,
                    'line' => $line,
                    'message' => $message,
                ];
            }
        }

        /** @var array<string, mixed> $result */
        $result = [
            'result' => $testResult->wasSuccessful() && !$noTestsFoundAndFailsOnEmpty ? 'passed' : 'failed',
            'tests' => $tests,
            'passed' => $tests - $failedCount - $erroredCount - $skipped,
            'assertions' => $assertions,
            'duration_ms' => $durationMs,
        ];

        if ($hasNoTests) {
            $result['raw'] = ['No tests found.'];
        }

        if ($failedCount > 0) {
            $result['failed'] = $failedCount;
            $result['failures'] = $failureDetails;
        }

        if ($erroredCount > 0) {
            $result['errors'] = $erroredCount;
            $result['error_details'] = $errorDetails;
        }

        if ($skipped > 0) {
            $result['skipped'] = $skipped;
        }

        if ($incomplete > 0) {
            $result['incomplete'] = $incomplete;
        }

        if ($deprecations > 0) {
            $result['deprecations'] = $deprecations;
            $result['deprecation_details'] = $this->extractIssueDetails(
                [...$testResult->deprecations(), ...$testResult->phpDeprecations()],
            );
        }

        if ($warnings > 0) {
            $result['warnings'] = $warnings;
            $result['warning_details'] = $this->extractIssueDetails(
                [...$testResult->warnings(), ...$testResult->phpWarnings()],
            );
        }

        if ($notices > 0) {
            $result['notices'] = $notices;
            $result['notice_details'] = $this->extractIssueDetails(
                [...$testResult->notices(), ...$testResult->phpNotices()],
            );
        }

        $phpErrors = $testResult->errors();

        if ($phpErrors !== []) {
            $result['php_errors'] = count($phpErrors);
            $result['php_error_details'] = $this->extractIssueDetails($phpErrors);
        }

        if ($risky > 0) {
            $result['risky'] = $risky;
        }

        if ($ignoredByBaseline > 0) {
            $result['ignored_by_baseline'] = $ignoredByBaseline;
        }

        $profileEntries = ProfileCollector::entries();

        if ($profileEntries !== []) {
            usort($profileEntries, fn(array $a, array $b): int => $b['duration_ms'] <=> $a['duration_ms']);
            $result['profile'] = array_slice($profileEntries, 0, 10);
        }

        return $result;
    }

    /**
     * Check whether PHPUnit is configured to fail on an empty test suite.
     *
     * @return bool
     */
    private function failsOnEmptyTestSuite(): bool
    {
        try {
            return ConfigurationRegistry::get()->failOnEmptyTestSuite();
        } catch (PhpThrowable) {
            return true;
        }
    }

    /**
     * Convert PHPUnit issues into file/line/message details.
     *
     * @param list<\PHPUnit\TestRunner\TestResult\Issues\Issue> $issues
     * @return list<array{file: string, line: int, message: string}>
     */
    private function extractIssueDetails(array $issues): array
    {
        $details = [];

        foreach ($issues as $issue) {
            $details[] = [
                'file' => $issue->file(),
                'line' => $issue->line(),
                'message' => $issue->description(),
            ];
        }

        return $details;
    }

    /**
     * Attempt to resolve the best available file/line for a test failure.
     *
     * @return array{string, int}
     */
    private function resolveTestLocation(string $file, int $line, Throwable $throwable): array
    {
        $isReal = $line > 0 && !str_contains($file, "eval()'d code");

        if ($isReal) {
            return [$file, $line];
        }

        $text = $throwable->description() . "\n" . $throwable->stackTrace();

        if (preg_match('/\bat\s+(.+\.php):(\d+)/', $text, $matches) === 1) {
            return [$matches[1], (int)$matches[2]];
        }

        if (preg_match('#([\w/\\\\._-]+\.php):(\d+)#', $throwable->stackTrace(), $matches) === 1) {
            return [$matches[1], (int)$matches[2]];
        }

        return [$file, $line];
    }
}
