<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Cake\Core\Configure;
use PHPUnit\Framework\TestCase;

use function Cake\Collection\collection;

final class VendorFramesTest extends TestCase
{
    public function test_it_fails_through_vendor_frames(): void
    {
        collection(['payload'])->each(function (): void {
            collection(['payload'])->each(function (): void {
                collection(['payload'])->each(function (): void {
                    collection(['payload'])->each(function (): void {
                        $this->assertSame('expected', 'actual');
                    });
                });
            });
        });
    }

    public function test_it_errors_inside_a_vendor_frame(): void
    {
        Configure::readOrFail('Essentia.Missing');
    }
}
