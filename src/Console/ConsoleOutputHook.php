<?php
declare(strict_types=1);

namespace Crustum\Essentia\Console;

use Cake\Console\ConsoleOutput;

/**
 * Replaces Cake ConsoleOutput with Essentia cleaned output in agent mode.
 *
 * @internal
 * @codeCoverageIgnore
 */
final class ConsoleOutputHook
{
    private static bool $registered = false;

    /**
     * Register a prepended autoloader that aliases Cake ConsoleOutput to Essentia output.
     *
     * @return void
     */
    public static function register(): void
    {
        if (self::$registered || class_exists(ConsoleOutput::class, false)) {
            return;
        }

        self::$registered = true;

        spl_autoload_register(static function (string $class): void {
            if ($class !== 'Cake\\Console\\ConsoleOutput') {
                return;
            }

            if (class_exists(ConsoleOutput::class, false)) {
                return;
            }

            $vendorPath = self::resolveVendorConsoleOutputPath();

            if ($vendorPath === null) {
                return;
            }

            $code = file_get_contents($vendorPath);

            if ($code === false) {
                return;
            }

            $code = str_replace('class ConsoleOutput', 'class VendorConsoleOutput', $code);

            $temporaryFile = tempnam(sys_get_temp_dir(), 'essentia_cake_console_output_');

            if ($temporaryFile === false) {
                return;
            }

            file_put_contents($temporaryFile, $code);
            require_once $temporaryFile;

            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }

            require_once __DIR__ . '/EssentiaConsoleOutput.php';
            class_alias(EssentiaConsoleOutput::class, 'Cake\\Console\\ConsoleOutput');
        }, prepend: true);
    }

    /**
     * Locate the installed CakePHP ConsoleOutput class file.
     *
     * @return non-empty-string|null
     */
    private static function resolveVendorConsoleOutputPath(): ?string
    {
        $directory = __DIR__;

        for ($depth = 0; $depth < 8; $depth++) {
            $path = $directory . '/vendor/cakephp/cakephp/src/Console/ConsoleOutput.php';

            if (is_file($path)) {
                return $path;
            }

            $directory = dirname($directory);
        }

        return null;
    }
}
