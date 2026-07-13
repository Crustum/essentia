<?php
declare(strict_types=1);

namespace Crustum\Essentia\Drivers\Paratest;

use Crustum\Essentia\Drivers\Concerns\TestResultParsableTrait;
use Crustum\Essentia\Drivers\Starter as BaseStarter;

/**
 * Paratest driver starter.
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
        return 'paratest';
    }

    /**
     * Prepare Paratest for structured result parsing.
     *
     * @return void
     */
    public function start(): void
    {
        $this->registerNullFilter();
        $this->startTimer();
        $this->registerExecutionFinishedSubscriber();
        $this->silenceStdout();

        /** @var list<string> $serverArgv */
        $serverArgv = $_SERVER['argv'];

        $argv = $serverArgv;

        $argv[] = '--runner';
        $argv[] = WrapperRunner::class;

        $_SERVER['argv'] = $argv;
    }
}
