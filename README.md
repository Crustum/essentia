# CakePHP Essentia Plugin

The **Essentia** plugin provides agent-optimized output for PHP tools used in CakePHP projects. It detects when PHPUnit, Pest, Paratest, PHPStan, Rector, PHPCS, or the CakePHP console are running inside an AI agent — **Claude Code**, **Cursor**, **Devin**, **Gemini CLI**, and others — and replaces verbose human-readable output with compact, structured JSON.

For CakePHP console commands, Essentia strips ANSI colors, box-drawing characters, and excess whitespace while keeping pass-through text output. Zero configuration — install as a dev dependency and it works automatically through Composer's autoloader.

Essentia only activates when it detects an AI agent. When you or your team run tools directly in the terminal, output is completely unchanged — same colors, same formatting, same experience.

## Requirements

* PHP 8.4+
* CakePHP 5.0+

See [Versions.md](docs/Versions.md) for the supported CakePHP versions.

## Documentation

For documentation, as well as tutorials, see the [docs](docs/index.md) directory of this repository.

## License

Licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) License. Redistributions of the source code included in this repository must retain the copyright notice found in each file.
