<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\TestCase;

final class AfterClassHookTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        self::assertTrue(false, 'Failure inside the afterClass hook');
    }

    public function test_it_passes(): void
    {
        $this->assertTrue(true);
    }
}
