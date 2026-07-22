# Essentia — Agent-Optimized Output

- [Introduction](#introduction)
- [Installation](#installation)
- [How It Works](#how-it-works)
- [Supported Tools](#supported-tools)
- [Before & After](#before--after)
- [PHPUnit, Pest & Paratest](#phpunit-pest--paratest)
- [PHPStan](#phpstan)
- [Rector](#rector)
- [PHPCS](#phpcs)
- [CakePHP Console](#cakephp-console)
- [Environment Variables](#environment-variables)
- [Disabling Essentia](#disabling-essentia)

<a name="introduction"></a>
## Introduction

**CakePHP Essentia** is agent-optimized output for PHP tools. It works with any CakePHP project that uses **PHPUnit**, **Pest**, **Paratest**, **PHPStan**, **Rector**, **PHPCS**, or the **CakePHP console**.

It detects when your tools are running inside an AI agent — **Claude Code**, **Cursor**, **Devin**, **Gemini CLI**, and others — and replaces verbose, human-readable output with compact, structured JSON. For CakePHP console commands, it strips ANSI colors, box-drawing characters, and excess whitespace. Zero config — just install and it works.

> **Essentia only activates when it detects an AI agent.** When you or your team run tools directly in the terminal, the output is completely unchanged — same colors, same formatting, same experience. Zero impact on human workflows.

<a name="installation"></a>
## Installation

### Requirements

- PHP 8.2+
- CakePHP 5.0+
- PHPUnit 11+, Pest 3+, Paratest, PHPStan, Rector, or PHPCS (as dev dependencies)

> [!NOTE]
> Parallel Paratest (`brianium/paratest` ^7.20) and Pest `--parallel` target PHP 8.3+.

### Installation via Composer

Install Essentia as a dev dependency in your CakePHP application or plugin:

```bash
composer require crustum/essentia --dev
```

That's it. Essentia hooks into PHPUnit, Pest, Paratest, PHPStan, Rector, and PHPCS automatically through Composer's autoload file (`src/Autoload.php`). No plugin bootstrap or `Application.php` changes are required.

For CakePHP console output cleaning, Essentia registers automatically when `bin/cake.php` (or `bin/cake.bat` on Windows) is invoked inside an agent environment.

<a name="how-it-works"></a>
## How It Works

Essentia uses the [agent-detector](https://github.com/laravel/agent-detector) package to detect AI agent environments (Cursor, Claude Code, Devin, Gemini CLI, and others).

When an agent is detected:

1. Essentia identifies the running tool from the CLI binary (`phpunit`, `pest`, `phpstan`, `cake.php`, etc.).
2. A tool-specific driver captures and suppresses verbose output during execution.
3. At shutdown, Essentia emits a single line of compact JSON with the result summary.
4. On failures, structured details (file paths, line numbers, messages) are included.

When no agent is detected, Essentia does nothing — your tools behave exactly as before.

<a name="supported-tools"></a>
## Supported Tools

| Tool | Driver | Agent output |
|------|--------|--------------|
| PHPUnit | `phpunit` | Structured JSON |
| Pest | `pest` | Structured JSON |
| Paratest | `paratest` | Structured JSON |
| PHPStan | `phpstan` | Structured JSON |
| Rector | `rector` | Structured JSON |
| PHPCS | `phpcs` | Structured JSON |
| CakePHP console | `cake` | Cleaned text (no JSON) |

<a name="before--after"></a>
## Before & After

Your test suite with **1,000 tests** goes from this:

```
PHPUnit 12.5.14 by Sebastian Bergmann and contributors.

.............................................................   61 / 1002 (  6%)
.............................................................  122 / 1002 ( 12%)
...
..........................                                    1002 / 1002 (100%)

Time: 00:00.321, Memory: 46.50 MB

OK (1002 tests, 1002 assertions)
```

To this:

```json
{"tool":"phpunit","result":"passed","tests":1002,"passed":1002,"duration_ms":321}
```

That's up to **99.8% fewer AI tokens**. The output is **constant-size** regardless of how many tests you have — and when tests fail, it includes file paths, line numbers, and failure messages.

Extra output from Pest plugins like `--coverage` or `--profile` is captured, cleaned of ANSI codes and decorations, and included as a `raw` array in the JSON:

```json
{
  "tool": "pest",
  "result": "passed",
  "tests": 1002,
  "passed": 1002,
  "duration_ms": 1520,
  "raw": [
    "Http/Controllers/Controller 100.0%",
    "Models/User 0.0%",
    "Total: 33.3 %"
  ]
}
```

<a name="phpunit-pest--paratest"></a>
## PHPUnit, Pest & Paratest

All three test runners produce the same compact JSON shape on success:

```json
{"tool":"pest","result":"passed","tests":129,"passed":129,"duration_ms":843}
```

On failure, Essentia includes structured failure details:

```json
{
  "tool": "pest",
  "result": "failed",
  "tests": 129,
  "passed": 128,
  "failed": 1,
  "duration_ms": 901,
  "failures": [
    {
      "test": "it validates input",
      "file": "tests/Unit/ExampleTest.php",
      "line": 12,
      "message": "Failed asserting that false is true."
    }
  ]
}
```

<a name="phpstan"></a>
## PHPStan

PHPStan output is converted to structured JSON:

```json
{
  "tool": "phpstan",
  "result": "failed",
  "errors": 2,
  "error_details": {
    "src/Controller/AppController.php": [
      {
        "line": 9,
        "message": "Method AppController::index() should return int but returns string.",
        "identifier": "return.type"
      },
      {
        "line": 14,
        "message": "Call to an undefined method AppController::doesNotExist().",
        "identifier": "method.notFound"
      }
    ]
  }
}
```

<a name="rector"></a>
## Rector

Rector is automatically run with its native JSON output format:

```json
{
  "tool": "rector",
  "result": "failed",
  "totals": {
    "changed_files": 1,
    "errors": 0
  },
  "file_diffs": [
    {
      "file": "src/Model/Entity/User.php",
      "diff": "--- Original\n+++ New\n@@ ...",
      "applied_rectors": [
        "Rector\\Php54\\Rector\\Array_\\LongArrayToShortArrayRector"
      ]
    }
  ],
  "changed_files": [
    "src/Model/Entity/User.php"
  ]
}
```

<a name="phpcs"></a>
## PHPCS

PHPCS output is converted to compact structured JSON. On a clean run, only totals are emitted — no file listing:

```json
{"tool":"phpcs","result":"passed","errors":0,"warnings":0}
```

When issues are found, only files with errors or warnings are included:

```json
{
  "tool": "phpcs",
  "result": "failed",
  "errors": 1,
  "warnings": 0,
  "files": {
    "src/Controller/AppController.php": {
      "errors": 1,
      "warnings": 0,
      "messages": [
        {
          "message": "Missing doc comment for function index()",
          "source": "CakePHP.Commenting.FunctionComment.Missing",
          "severity": 5,
          "fixable": false,
          "type": "ERROR",
          "line": 21,
          "column": 12
        }
      ]
    }
  }
}
```

<a name="cakephp-console"></a>
## CakePHP Console

When running CakePHP console commands inside an agent environment, Essentia cleans command output — stripping ANSI colors, box-drawing characters, dot separators, and excess whitespace:

```
# Before (without Essentia) — verbose decoration
  Connectors ............................................................. 16

# After (with Essentia) — same information, no decoration
 Connectors: 16
```

CakePHP console output remains human-readable text. Essentia does not wrap console output in JSON because command output varies widely across commands.

Supported invocations:

```bash
bin/cake.php migrations status
bin/cake saloon list
```

On Windows, `bin/cake.bat` is also supported.

<a name="environment-variables"></a>
## Environment Variables

| Variable | Description |
|----------|-------------|
| `CURSOR_AGENT=1` | Detected automatically by agent-detector in Cursor |
| `ESSENTIA_FORCE=1` | Force Essentia activation even outside an agent environment (useful for testing) |
| `ESSENTIA_DISABLE=1` | Disable Essentia entirely |

Example — test Essentia output locally:

```bash
ESSENTIA_FORCE=1 composer test
```

On Windows (PowerShell):

```powershell
$env:ESSENTIA_FORCE='1'; composer test
```

<a name="disabling-essentia"></a>
## Disabling Essentia

To disable Essentia for a single command:

```bash
ESSENTIA_DISABLE=1 composer test
```

To disable Essentia for an entire session:

```bash
export ESSENTIA_DISABLE=1
```

On Windows (PowerShell):

```powershell
$env:ESSENTIA_DISABLE='1'
```
