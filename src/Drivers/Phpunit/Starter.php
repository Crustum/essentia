<?php
declare(strict_types=1);

namespace Crustum\Essentia\Drivers\Phpunit;

use Crustum\Essentia\Drivers\Concerns\TestResultParsableTrait;
use Crustum\Essentia\Drivers\Starter as BaseStarter;

/**
 * PHPUnit driver starter.
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
        return 'phpunit';
    }

    /**
     * Prepare PHPUnit for structured result parsing.
     *
     * @return void
     */
    public function start(): void
    {
        $this->registerNullFilter();
        $this->startTimer();
        $this->registerExecutionFinishedSubscriber();
        $this->registerProfileSubscriber();

        /** @var list<string> $serverArgv */
        $serverArgv = $_SERVER['argv'];

        $argv = $serverArgv;

        if (!in_array('--no-output', $argv, true)) {
            $argv[] = '--no-output';
        }

        $_SERVER['argv'] = $argv;
    }
}
