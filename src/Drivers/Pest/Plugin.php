<?php
declare(strict_types=1);

namespace Crustum\Essentia\Drivers\Pest;

use Crustum\Essentia\Execution;
use Pest\Contracts\Plugins\HandlesArguments;

/**
 * Pest plugin to adjust arguments in agent mode.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class Plugin implements HandlesArguments
{
    /**
     * Append arguments to reduce Pest output in agent mode.
     *
     * @param array<int, string> $arguments
     * @return array<int, string>
     */
    public function handleArguments(array $arguments): array
    {
        if (!Execution::running()) {
            return $arguments;
        }

        if (!in_array('--no-output', $arguments, true)) {
            $arguments[] = '--no-output';
        }

        if (!in_array('--no-progress', $arguments, true)) {
            $arguments[] = '--no-progress';
        }

        return $arguments;
    }
}
