# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0]

### Added

- StructArmed support in two modes:
  - CLI driver — `structarmed analyse` output converted to structured JSON (`{"tool":"structarmed","result":"failed","total":N,"violations":[...]}`)
  - PHPUnit extension — `Crustum\Essentia\StructArmed\StructArmedExtension` registers in `phpunit.xml`; each run's architecture violations are appended to the phpunit/pest/paratest JSON as a `structarmed` key and mark the run failed
- PHPStan raw fallback — surfaces stderr/stdout as raw lines when JSON is unavailable (config missing, invalid options)
- PHPStan transform gating — only `analyse`/`analyze` runs are transformed; `--fix`, `--watch`, `--pro`, `--generate-baseline`/`-b`, version/help are left untouched
- Rector transform gating — only `process` runs are transformed; non-process commands pass through
- Rector fatal-errors output — `fatal_errors` surfaced separately instead of being buried in raw output
- Stderr is captured instead of silenced (`StderrCaptureFilter`) so driver errors surface in agent output
- `--error-format=json`, `--no-progress`, `--output-format=json` injected before the `--` end-of-options separator
- Paratest `WrapperRunner` merges deprecation-summary counters for PHPUnit >= 13.3 (fixes `ArgumentCountError` on the new `TestResult` constructor)
- Pest does not repeat `--no-output`/`--no-progress` the caller already passed
- Early-exit on `-V` in `Autoload`
- Composer dev-dependency floors bumped for PHPUnit >= 13.3 support (`pest ^4.7.8 || ^5.1.0`, `phpstan ^2.2.8`, `rector ^2.6.1`, `paratest ^7.20`)

## [1.0.1] - 2026-07-22

### Changed

- Minimum PHP version lowered to 8.2+ so dependents can install on PHP 8.2
- Widened Pest and Paratest version ranges for PHP 8.2 compatibility
- Improved PHPUnit / Pest / Paratest result parsing (hook failures, location resolution, structured details)

## [1.0.0]

### Added

- Agent-optimized output for PHPUnit, Pest, Paratest, PHPStan, Rector, PHPCS, and CakePHP console
- Automatic activation via Composer autoload when an AI agent is detected (Claude Code, Cursor, Devin, Gemini CLI, and others)
- Compact structured JSON summaries for test and static-analysis tools; cleaned text for CakePHP console
- Failure details with file paths, line numbers, and messages when tools report errors
- Zero configuration for human terminals — output unchanged when no agent is detected
- Environment overrides: `ESSENTIA_FORCE` and `ESSENTIA_DISABLE`
- Documentation covering installation, supported tools, and before/after examples
