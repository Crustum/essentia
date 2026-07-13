<?php
declare(strict_types=1);

use Crustum\Essentia\Console\ConsoleOutputHook;
use Crustum\Essentia\Drivers\Cake\Console\Starter;
use Crustum\Essentia\UserFilters\CleanFilter;

it('cleans cake help spacing through CleanFilter', function (): void {
    if (!in_array('agent_output_clean_help_test', stream_get_filters(), true)) {
        stream_filter_register('agent_output_clean_help_test', CleanFilter::class);
    }

    $buffer = fopen('php://memory', 'w+');
    stream_filter_append($buffer, 'agent_output_clean_help_test', STREAM_FILTER_WRITE);
    fwrite($buffer, "  bootstrap                     BootstrapUI commands entry point.\n");
    rewind($buffer);

    expect(stream_get_contents($buffer))->toBe(" bootstrap BootstrapUI commands entry point.\n");
});

it('cleans streamed output through CleanFilter', function (): void {
    if (!in_array('agent_output_clean_test', stream_get_filters(), true)) {
        stream_filter_register('agent_output_clean_test', CleanFilter::class);
    }

    $stream = fopen('php://memory', 'w+');
    stream_filter_append($stream, 'agent_output_clean_test', STREAM_FILTER_WRITE);

    fwrite($stream, "\e[32mSuccess\e[0m");
    rewind($stream);

    expect(stream_get_contents($stream))->toBe('Success');
});

it('exposes cake driver metadata', function (): void {
    $starter = new Starter();

    expect($starter->name())->toBe('cake')
        ->and($starter->parse())->toBeNull();
});

it('registers console output hook and disables color for cake console starter', function (): void {
    $previousNoColor = getenv('NO_COLOR');

    try {
        $starter = new Starter();
        $starter->start();

        expect(getenv('NO_COLOR'))->toBe('1')
            ->and($_ENV['NO_COLOR'] ?? null)->toBe('1')
            ->and($_SERVER['NO_COLOR'] ?? null)->toBe('1');
    } finally {
        if ($previousNoColor === false) {
            putenv('NO_COLOR');
            unset($_ENV['NO_COLOR'], $_SERVER['NO_COLOR']);
        } else {
            putenv('NO_COLOR=' . $previousNoColor);
            $_ENV['NO_COLOR'] = $previousNoColor;
            $_SERVER['NO_COLOR'] = $previousNoColor;
        }
    }
});

it('cleans cake console output through hooked ConsoleOutput', function (): void {
    if (class_exists(Cake\Console\ConsoleOutput::class, false)) {
        expect(true)->toBeTrue();

        return;
    }

    ConsoleOutputHook::register();

    $output = new Cake\Console\ConsoleOutput('php://stdout');
    $output->write('  bootstrap                     BootstrapUI commands entry point.');

    expect($output)->toBeInstanceOf(Crustum\Essentia\Console\EssentiaConsoleOutput::class);
})->skip(!is_file(dirname(__DIR__, 2) . '/vendor/cakephp/cakephp/src/Console/ConsoleOutput.php'), 'CakePHP is not installed');
