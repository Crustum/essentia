<?php
declare(strict_types=1);

namespace Crustum\Essentia\Contracts;

/**
 * Driver interface for tool integrations.
 *
 * @internal
 */
interface Driver
{
    /**
     * Start the driver.
     *
     * @return void
     */
    public function start(): void;

    /**
     * Get the name of the driver.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Parse the output of the driver.
     *
     * @return array<string, mixed>|null
     */
    public function parse(): ?array;
}
