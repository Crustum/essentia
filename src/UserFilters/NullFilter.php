<?php
declare(strict_types=1);

namespace Crustum\Essentia\UserFilters;

use php_user_filter;

/**
 * Stream filter that discards output.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class NullFilter extends php_user_filter
{
    /**
     * Filter the input.
     *
     * @param resource $in
     * @param resource $out
     * @param int $consumed
     * @return int
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public function filter($in, $out, &$consumed, bool $closing): int // @pest-ignore-type
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            /** @var int $datalen */
            $datalen = $bucket->datalen;
            $consumed += $datalen;
            $bucket->data = '';
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
