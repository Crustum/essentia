<?php
declare(strict_types=1);

namespace Crustum\Essentia\Exceptions;

use RuntimeException;

/**
 * Internal invariant violation exception.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class ShouldNotHappenException extends RuntimeException
{
    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('This should not have happened. Please report this issue.');
    }
}
