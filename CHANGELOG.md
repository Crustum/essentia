# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
