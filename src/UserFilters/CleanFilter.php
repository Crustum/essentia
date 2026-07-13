<?php
declare(strict_types=1);

namespace Crustum\Essentia\UserFilters;

use Crustum\Essentia\OutputCleaner;
use php_user_filter;

/**
 * Stream filter that cleans stdout/stderr output as it is written.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class CleanFilter extends php_user_filter
{
    /**
     * Clean streamed output chunks before passing them through.
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
            $bucket->data = OutputCleaner::clean($data);

            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
