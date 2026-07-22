<?php
declare(strict_types=1);

/** @codeCoverageIgnoreStart */

use Crustum\Essentia\Execution;
use Crustum\Essentia\OutputCleaner;
use Crustum\Essentia\UserFilters\CaptureFilter;
use Laravel\AgentDetector\AgentDetector;

/** @var array<int, string>|null $argv */
$argv = $_SERVER['argv'] ?? null;

if (!is_array($argv) || $argv === []) {
    return;
}

if (filter_var($_SERVER['ESSENTIA_DISABLE'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
    return;
}

$isDriverSubprocess = false;

foreach ($argv as $argument) {
    if (str_contains(str_replace('\\', '/', $argument), 'tests/Fixtures')) {
        $isDriverSubprocess = true;

        break;
    }
}

if (!$isDriverSubprocess) {
    $composerPath = getcwd() . DIRECTORY_SEPARATOR . 'composer.json';

    if (is_readable($composerPath)) {
        $composer = json_decode((string)file_get_contents($composerPath), true);

        if (is_array($composer) && ($composer['name'] ?? '') === 'crustum/essentia') {
            $binary = basename($argv[0] ?? '');

            if (in_array($binary, ['pest', 'pest.bat', 'phpunit', 'phpunit.bat', 'php', 'php.exe'], true)) {
                return;
            }
        }
    }
}

$paratest = $_SERVER['PARATEST'] ?? $_ENV['PARATEST'] ?? getenv('PARATEST');

$isParatestWorker = $paratest !== false
    && $paratest !== ''
    && filter_var($paratest, FILTER_VALIDATE_INT) === 1;

if ($isParatestWorker) {
    return;
}

$agent = AgentDetector::detect();

if (!$agent->isAgent && !filter_var($_SERVER['ESSENTIA_FORCE'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
    return;
}

if (array_intersect($argv, ['--version', '--help', '-h', 'worker'])) {
    return;
}

unset($_SERVER['COLLISION_PRINTER']);
$_SERVER['PEST_PARALLEL_NO_OUTPUT'] = '1';

register_shutdown_function(static function (): void {
    if (!Execution::running()) {
        return;
    }

    $execution = Execution::current();

    $result = $execution->driver->parse() ?: [];

    $captured = trim(CaptureFilter::output());

    $execution->restoreStdout();

    if ($captured !== '') {
        $captured = OutputCleaner::clean($captured);

        $lines = array_values(array_filter(
            array_map(trim(...), explode("\n", $captured)),
            static fn(string $line): bool => $line !== ''
                && !preg_match('/^[.st!]+$/', $line)
                && !preg_match('/^(Tests:|Duration:|Parallel:|Time:|Generating code coverage)\s/', $line)
                && !preg_match('/^(INFO\s+)?No tests found\.?$/i', $line)
                && !str_ends_with($line, 'by Sebastian Bergmann and contributors.'),
        ));

        if ($lines !== []) {
            $existing = is_array($result['raw'] ?? null) ? array_values($result['raw']) : [];

            $result['raw'] = [...$existing, ...$lines];
        }
    }

    if ($result !== []) {
        $result = ['tool' => $execution->driver->name()] + $result;

        fwrite(
            STDOUT,
            json_encode(
                $result,
                JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
            ) . PHP_EOL,
        );

        if (($result['result'] ?? '') === 'failed' && (int)($result['tests'] ?? 0) === 0) {
            exit(1);
        }
    }
});

Execution::start($agent, $argv);

/** @codeCoverageIgnoreEnd */
