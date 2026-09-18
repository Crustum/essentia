<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('surfaces structarmed violations inside a phpunit run', function (): void {
    $process = new Process(
        [PHP_BINARY, 'vendor/bin/phpunit', '--configuration', 'tests/Fixtures/StructArmed/phpunit.xml'],
        dirname(__DIR__, 2),
        isolatedProcessEnvironment(),
    );
    $process->run();

    $output = decodeOutput($process);

    expect($output['tool'])->toBe('phpunit')
        ->and($output['tests'])->toBe(1)
        ->and($output['result'])->toBe('failed')
        ->and($output['structarmed']['passed'])->toBeFalse()
        ->and($output['structarmed']['total'])->toBe(1)
        ->and($output['structarmed']['violations'][0]['rule'])->toBe('ruleset.Core')
        ->and($output['structarmed']['violations'][0]['message'])->toContain('must not depend on')
        ->and($output['structarmed']['violations'][0]['file'])->toEndWith('CoreViolator.php')
        ->and($output['structarmed']['violations'][0]['line'])->toBe(9)
        ->and($output['structarmed']['violations'][0]['class'])->toBe('Fixtures\\StructArmed\\Core\\CoreViolator')
        ->and($output['structarmed']['violations'][0]['layer'])->toBe('Core');
});

it('surfaces structarmed violations inside a pest run', function (): void {
    $process = new Process(
        [PHP_BINARY, 'vendor/bin/pest', '--configuration', 'tests/Fixtures/StructArmedPest/phpunit.xml'],
        dirname(__DIR__, 2),
        isolatedProcessEnvironment(),
    );
    $process->run();

    $output = decodeOutput($process);

    expect($output['tool'])->toBe('pest')
        ->and($output['tests'])->toBe(1)
        ->and($output['result'])->toBe('failed')
        ->and($output['structarmed']['passed'])->toBeFalse()
        ->and($output['structarmed']['total'])->toBe(1)
        ->and($output['structarmed']['violations'][0]['rule'])->toBe('ruleset.Core')
        ->and($output['structarmed']['violations'][0]['layer'])->toBe('Core');
});

it('keeps the normal phpunit json when no structarmed extension is registered', function (): void {
    $output = decodeOutput(runWith('phpunit', 'PassingTest'));

    expect($output['result'])->toBe('passed')
        ->and($output)->not->toHaveKey('structarmed');
});
