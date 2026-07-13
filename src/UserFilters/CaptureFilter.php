<?php
declare(strict_types=1);

namespace Crustum\Essentia\UserFilters;

use php_user_filter;

/**
 * Stream filter that captures stdout for later processing.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class CaptureFilter extends php_user_filter
{
    private static string $captured = '';

    /**
     * Intercept stdout and store it for later parsing.
     *
     * @param resource $in
     * @param resource $out
     * @param int $consumed
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     * @return int
     */
    public function filter($in, $out, &$consumed, bool $closing): int // @pest-ignore-type
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            /** @var int $datalen */
            $datalen = $bucket->datalen;
            $consumed += $datalen;

            /** @var string $data */
            $data = $bucket->data;
            self::$captured .= $data;

            $bucket->data = '';
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }

    /**
     * Get the captured output.
     *
     * @return string
     */
    public static function output(): string
    {
        return self::$captured;
    }

    /**
     * Append captured output.
     *
     * @param string $data
     * @return void
     */
    public static function append(string $data): void
    {
        self::$captured .= $data;
    }

    /**
     * Reset captured output to an empty buffer.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$captured = '';
    }
}
