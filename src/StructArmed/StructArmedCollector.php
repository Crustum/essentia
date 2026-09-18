<?php
declare(strict_types=1);

namespace Crustum\Essentia\StructArmed;

use Boundwize\StructArmed\Rule\RuleViolationCollection;

/**
 * Collects StructArmed violations reported during a PHPUnit run.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class StructArmedCollector
{
    private static ?RuleViolationCollection $collection = null;

    /**
     * Record the analysis result from the extension.
     *
     * @param \Boundwize\StructArmed\Rule\RuleViolationCollection $collection
     * @return void
     */
    public static function set(RuleViolationCollection $collection): void
    {
        self::$collection = $collection;
    }

    /**
     * Get the collected analysis result.
     *
     * @return \Boundwize\StructArmed\Rule\RuleViolationCollection|null
     */
    public static function get(): ?RuleViolationCollection
    {
        return self::$collection;
    }

    /**
     * Reset the collected analysis result.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$collection = null;
    }
}
