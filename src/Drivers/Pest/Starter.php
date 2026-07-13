<?php
declare(strict_types=1);

namespace Crustum\Essentia\Drivers\Pest;

use Crustum\Essentia\Drivers\Concerns\ProfileCollector;
use Crustum\Essentia\Drivers\Concerns\TestResultParsableTrait;
use Crustum\Essentia\Drivers\Starter as BaseStarter;

/**
 * Pest driver starter.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class Starter extends BaseStarter
{
    use TestResultParsableTrait;

    /**
     * Get the tool name for this driver.
     *
     * @return string
     */
    public function name(): string
    {
        return 'pest';
    }

    /**
     * Prepare the environment for parsing Pest execution results.
     *
     * @return void
     */
    public function start(): void
    {
        $this->registerNullFilter();
        $this->startTimer();
        $this->registerExecutionFinishedSubscriber();
        $this->saveStdout();
        $this->silenceStdout();

        /** @var list<string> $argv */
        $argv = $_SERVER['argv'] ?? [];

        if (in_array('--parallel', $argv, true)) {
            ProfileCollector::startTimerFromNanoseconds(hrtime(true));
            ProfileCollector::executionStarted();
        } else {
            $this->registerProfileSubscriber();
        }
    }
}
