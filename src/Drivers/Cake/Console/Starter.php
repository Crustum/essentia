<?php
declare(strict_types=1);

namespace Crustum\Essentia\Drivers\Cake\Console;

use Crustum\Essentia\Console\ConsoleOutputHook;
use Crustum\Essentia\Drivers\Starter as BaseStarter;

/**
 * Cake console driver starter.
 *
 * Cleans command output for agent consumption without structured JSON.
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
        return 'cake';
    }

    /**
     * Prepare Cake console output for agent-friendly pass-through cleaning.
     *
     * @return void
     */
    public function start(): void
    {
        putenv('NO_COLOR=1');
        $_ENV['NO_COLOR'] = '1';
        $_SERVER['NO_COLOR'] = '1';

        ConsoleOutputHook::register();
    }

    /**
     * Cake console output is pass-through cleaned, not parsed to JSON.
     *
     * @return array<string, mixed>|null
     */
    public function parse(): ?array
    {
        return null;
    }
}
