<?php

declare(strict_types=1);

it('fails inside a nested closure', function (): void {
    array_map(function (int $value): void {
        expect($value)->toBe(2);
    }, [1]);
});
