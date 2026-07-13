<?php
declare(strict_types=1);

namespace Crustum\Essentia;

/**
 * Output cleanup utility to remove ANSI escapes and visual noise.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class OutputCleaner
{
    /**
     * Clean the output.
     *
     * @param string $output
     * @return string
     */
    public static function clean(string $output): string
    {
        $output = (string)preg_replace('/\e\[[0-9;]*[A-Za-z]/', '', $output);
        $output = (string)preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $output);
        $output = (string)preg_replace('/\x{FFFD}/u', '', $output);
        $output = (string)preg_replace('/[─━│┌┐└┘├┤┬┴┼▓░▒═║╔╗╚╝╠╣╦╩╬➜▶►⚠✖✔●◆■▪→←↑↓▕⨯✕]+/u', '', $output);
        $output = (string)preg_replace('/\.{3,}/', '..', $output);
        $output = (string)preg_replace('/[ \t]+/', ' ', $output);

        return (string)preg_replace('/\n\s*\n/', "\n", $output);
    }
}
