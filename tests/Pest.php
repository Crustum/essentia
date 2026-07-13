<?php
declare(strict_types=1);

use Laravel\AgentDetector\AgentDetector;
use Symfony\Component\Process\Process;

/**
 * @return array<string, mixed>
 */
function buildAgentEnvironment(bool $withAgent = true): array
{
    $env = ['AI_AGENT' => $withAgent ? '1' : false];

    foreach (array_keys(AgentDetector::AGENT_ENV_VARS) as $key) {
        $env[$key] = false;
    }

    return $env;
}

/**
 * Symfony Process merges the parent environment on Windows. Strip it so driver
 * subprocess tests behave the same via composer test and direct pest.
 *
 * @param array<string, mixed> $extraEnv
 * @return array<string, string|false>
 */
function isolatedProcessEnvironment(bool $withAgent = true, array $extraEnv = []): array
{
    $env = buildAgentEnvironment($withAgent);

    foreach (['PATH', 'PATHEXT', 'SYSTEMROOT', 'TEMP', 'TMP', 'WINDIR', 'ComSpec'] as $required) {
        $value = getenv($required);

        if ($value !== false) {
            $env[$required] = $value;
        }
    }

    $inherited = getenv();

    if (is_array($inherited)) {
        foreach (array_keys($inherited) as $key) {
            if (!array_key_exists($key, $env)) {
                $env[$key] = false;
            }
        }
    }

    return array_merge($env, $extraEnv);
}

/**
 * @param list<string> $extraArgs
 * @param array<string, mixed> $extraEnv
 * @return \Symfony\Component\Process\Process
 */
function runWith(
    string $binary,
    string $filter,
    bool $withAgent = true,
    array $extraArgs = [],
    string $config = 'tests/Fixtures/phpunit.xml',
    array $extraEnv = [],
): Process {
    $command = [PHP_BINARY, 'vendor/bin/' . $binary, '--configuration', $config, '--filter', $filter, ...$extraArgs];

    $process = new Process(
        command: $command,
        cwd: dirname(__DIR__),
        env: isolatedProcessEnvironment($withAgent, $extraEnv),
    );

    $process->run();

    return $process;
}

function cleanOutput(string $raw): string
{
    $raw = str_replace("\r", '', $raw);

    return (string)preg_replace('/\e\[[0-9;]*m/', '', trim($raw));
}

function decodeFromMixedOutput(Process $process): mixed
{
    $raw = cleanOutput($process->getOutput());

    $jsonStart = strpos($raw, '{"tool":');

    if ($jsonStart !== false && $jsonStart > 0) {
        $raw = substr($raw, $jsonStart);
    }

    return json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @param list<string> $extraArgs
 * @return \Symfony\Component\Process\Process
 */
function runPhpstan(string $configPath, bool $withAgent = true, array $extraArgs = []): Process
{
    $command = [PHP_BINARY, 'vendor/bin/phpstan', 'analyse', '--configuration', $configPath, ...$extraArgs];

    $process = new Process(
        command: $command,
        cwd: dirname(__DIR__),
        env: isolatedProcessEnvironment($withAgent),
    );

    $process->run();

    return $process;
}

/**
 * @param list<string> $extraArgs
 * @return \Symfony\Component\Process\Process
 */
function runRector(string $configPath, bool $withAgent = true, array $extraArgs = []): Process
{
    $env = [
        'AI_AGENT' => $withAgent ? '1' : false,
        'CLAUDECODE' => false,
        'CLAUDE_CODE' => false,
    ];

    $command = [PHP_BINARY, 'vendor/bin/rector', 'process', '--config', $configPath, ...$extraArgs];

    $process = new Process(
        command: $command,
        cwd: dirname(__DIR__),
        env: $env,
    );

    $process->run();

    return $process;
}

function decodeOutput(Process $process): mixed
{
    $raw = cleanOutput($process->getOutput());

    $decoded = json_decode($raw, associative: true);

    if ($decoded === null) {
        $jsonStart = strpos($raw, '{"tool":');
        if ($jsonStart !== false) {
            $decoded = json_decode(substr($raw, $jsonStart), associative: true);
        } else {
            $firstBrace = strpos($raw, '{');
            $lastBrace = strrpos($raw, '}');

            if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
                $decoded = json_decode(substr($raw, $firstBrace, $lastBrace - $firstBrace + 1), associative: true);
            }
        }
    }

    if ($decoded === null) {
        $stderr = $process->getErrorOutput();
        $exitCode = $process->getExitCode();
        $command = $process->getCommandLine();

        throw new RuntimeException(
            'Failed to decode JSON: ' . json_last_error_msg() . "\n" .
            sprintf('Command: %s%s', $command, PHP_EOL) .
            sprintf('Exit code: %s%s', $exitCode, PHP_EOL) .
            sprintf('OS: %s%s', PHP_OS_FAMILY, PHP_EOL) .
            sprintf('Raw output length: %s%s', strlen($process->getOutput()), PHP_EOL) .
            'STDOUT: ' . substr($process->getOutput(), 0, 2000) . "\n" .
            'STDERR: ' . substr($stderr, 0, 500),
        );
    }

    return $decoded;
}
