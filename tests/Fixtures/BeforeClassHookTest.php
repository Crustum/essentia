<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;

final class BeforeClassHookTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        self::assertTrue(false, 'Failure inside the beforeClass hook');
    }

    public function test_it_never_runs(): void
    {
        $this->assertTrue(true);
    }
}
