<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * @param list<string> $args
 */
function runStructarmed(array $args, bool $withAgent = true): Process
{
    $process = new Process(
        command: [PHP_BINARY, 'vendor/bin/structarmed', ...$args],
        cwd: dirname(__DIR__, 2),
        env: isolatedProcessEnvironment($withAgent),
    );

    $process->run();

    return $process;
}

it('outputs json for code with violations', function (): void {
    $output = decodeOutput(runStructarmed([
        'analyse', 'tests/Fixtures/StructArmed/src',
        '--config=tests/Fixtures/StructArmed/structarmed.php',
    ]));

    expect($output['tool'])->toBe('structarmed')
        ->and($output['result'])->toBe('failed')
        ->and($output['total'])->toBe(1)
        ->and($output['violations'])->toHaveCount(1)
        ->and($output['violations'][0]['rule'])->toBe('ruleset.Core')
        ->and($output['violations'][0]['message'])->toContain('must not depend on')
        ->and($output['violations'][0]['file'])->toEndWith('CoreViolator.php')
        ->and($output['violations'][0]['line'])->toBe(9)
        ->and($output['violations'][0]['layer'])->toBe('Core');
});

it('passes through normal output without agent', function (): void {
    $process = runStructarmed([
        'analyse', 'tests/Fixtures/StructArmed/src',
        '--config=tests/Fixtures/StructArmed/structarmed.php',
    ], withAgent: false);

    expect($process->getOutput())->not->toContain('"tool"')
        ->and($process->getOutput())->toContain('violation');
});

it('leaves non-analyse commands untouched', function (): void {
    $raw = runStructarmed(['--version'])->getOutput();

    expect($raw)->not->toContain('"tool":"structarmed"')
        ->and($raw)->toContain('StructArmed');
});
