<?php
declare(strict_types=1);

use Crustum\Essentia\Drivers\Phpcs\Starter;
use Crustum\Essentia\UserFilters\CaptureFilter;

it('returns compact phpcs output when there are no issues', function (): void {
    $starter = new Starter();
    $method = new ReflectionMethod($starter, 'parse');

    CaptureFilter::reset();

    $report = json_encode([
        'totals' => ['errors' => 0, 'warnings' => 0, 'fixable' => 0],
        'files' => [
            'src/Clean.php' => ['errors' => 0, 'warnings' => 0, 'messages' => []],
            'src/AlsoClean.php' => ['errors' => 0, 'warnings' => 0, 'messages' => []],
        ],
    ], JSON_THROW_ON_ERROR);

    injectCapturedOutput($report);

    expect($method->invoke($starter))->toBe([
        'result' => 'passed',
        'errors' => 0,
        'warnings' => 0,
    ]);
});

it('returns only files with phpcs issues', function (): void {
    $starter = new Starter();
    $method = new ReflectionMethod($starter, 'parse');

    CaptureFilter::reset();

    $report = json_encode([
        'totals' => ['errors' => 1, 'warnings' => 0, 'fixable' => 0],
        'files' => [
            'src/Clean.php' => ['errors' => 0, 'warnings' => 0, 'messages' => []],
            'src/Dirty.php' => [
                'errors' => 1,
                'warnings' => 0,
                'messages' => [
                    [
                        'message' => 'Missing docblock',
                        'source' => 'Generic.Comment',
                        'severity' => 5,
                        'fixable' => false,
                        'type' => 'ERROR',
                        'line' => 10,
                        'column' => 1,
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    injectCapturedOutput($report);

    expect($method->invoke($starter))->toBe([
        'result' => 'failed',
        'errors' => 1,
        'warnings' => 0,
        'files' => [
            'src/Dirty.php' => [
                'errors' => 1,
                'warnings' => 0,
                'messages' => [
                    [
                        'message' => 'Missing docblock',
                        'source' => 'Generic.Comment',
                        'severity' => 5,
                        'fixable' => false,
                        'type' => 'ERROR',
                        'line' => 10,
                        'column' => 1,
                    ],
                ],
            ],
        ],
    ]);
});

function injectCapturedOutput(string $output): void
{
    if (!in_array('agent_output_capture_test', stream_get_filters(), true)) {
        stream_filter_register('agent_output_capture_test', CaptureFilter::class);
    }

    CaptureFilter::reset();

    $stream = fopen('php://memory', 'w+');
    stream_filter_append($stream, 'agent_output_capture_test', STREAM_FILTER_WRITE);
    fwrite($stream, $output);
    fclose($stream);
}
