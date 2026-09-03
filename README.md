# rasuvaeff/understudy-psalm

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/understudy-psalm/v)](https://packagist.org/packages/rasuvaeff/understudy-psalm)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/understudy-psalm/downloads)](https://packagist.org/packages/rasuvaeff/understudy-psalm)
[![Build](https://github.com/rasuvaeff/understudy-psalm/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/understudy-psalm/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/understudy-psalm/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/understudy-psalm/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/understudy-psalm/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/understudy-psalm/php)](https://packagist.org/packages/rasuvaeff/understudy-psalm)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[Русская версия](README.ru.md)

Psalm plugin for [understudy](https://github.com/rasuvaeff/understudy).

> Using an AI coding assistant? Point it at [llms.txt](llms.txt).

## Requirements

- PHP 8.3 - 8.5
- `vimeo/psalm` ^6.16
- `rasuvaeff/understudy` ^0.1

## Installation

```bash
composer require --dev rasuvaeff/understudy-psalm
vendor/bin/psalm-plugin enable rasuvaeff/understudy-psalm
```

## What it does

understudy specifies a call by making it inside a closure:

```php
when(fn () => $repo->find(Arg::int(min: 1)))->returns($book);
```

`Arg::int()` is declared `mixed`, because a matcher has to be passable
wherever the contract declares anything at all. Psalm reports that as an
argument-type error — correctly, in general, and wrongly here.

The plugin drops that report inside a specification closure, and only there:

| Where | Psalm |
|---|---|
| `when(fn () => $repo->find(Arg::int()))` | silent |
| `Understudy::expect(fn () => $repo->find(Arg::any()))` | silent |
| `Understudy::calls(fn () => $repo->find(Arg::any()))` | silent |
| `Understudy::verifySequence(fn () => ..., fn () => ...)` | silent |
| `$repo->find(Arg::int())` — a real call | still an error |

The last row is the point. A matcher reaching a real call raises
`MatcherLeaked` at runtime, and a plugin that hid it would be worse than no
plugin at all.

Two understudy 0.4 idioms are covered the same way:

- **`Arg::rest()`** legitimately passes fewer arguments than the contract
  declares — `when(fn () => $storage->recordOutcome('svc', Arg::rest()))` —
  so `TooFewArguments` goes quiet on that call, but only when the call sits
  inside a specification *and* its last written argument is `Arg::rest()`. A
  real call ending in `Arg::rest()` is under-arity for real (the engine
  answers it with `ArgumentCountError`) and keeps both reports.
- **`Arg::captor()`**'s `$captor->capture()` is a matcher in method-call
  clothes: its argument-type report goes quiet inside a specification, it
  does not count against "exactly one call per closure", and a capture leaked
  into a real call keeps its report.

Which `Arg` those rows are about is decided by the resolver, never by the
written name: somebody else's `Acme\Console\Arg::int()` inside a specification
keeps its own diagnostics, and ours imported under an alias loses them under
any name. The one thing still read by shape is `capture()`, which is matched
as a zero-argument method call because the receiver's type is not available
where the report is intercepted — so a foreign zero-argument `capture()`
written inside a specification closure would be taken for a captor's.

## What else it reports

| Reported as | For |
|---|---|
| `UnderstudyMisuse` | A matcher whose kind the parameter can never accept. A closure that specifies nothing, or two calls, or calls a static method. Cardinality no run can satisfy. `verify()` arguments that contradict each other. |
| Psalm's own `InvalidArgument` | `returns()` and `answers()` against the method being specified. The plugin does not check these — it fills in the builder's template parameter, and `WhenBuilder<TReturn>` already declares `returns(TReturn ...)`. Psalm does the rest. |
| Psalm's own array and method diagnostics | `wire()`, whose shape is read from the named class's constructor: an unknown key is an error, and each double is typed as its contract. A dynamic class-string is left alone. |

Everything the plugin is not sure about stays silent. A false accusation costs
more than a missed one here, because the engine still catches what static
analysis misses.

## API

| Type | Purpose |
|---|---|
| `UnderstudyMisuse` | The issue every diagnostic of this plugin is reported as. One type rather than one per rule: you either have understudy analysing your specifications or you do not, and needing to silence each rule separately would be a worse contract than needing to silence none. |
| `Plugin` | The entry point Psalm loads, registered through `extra.psalm.pluginClass`. Nothing else in this package is public — the handlers it registers are `@internal`, and what they decide is the plugin's behaviour, not its API. |

## The understudy family

| Package | What it is |
|---|---|
| [rasuvaeff/understudy](https://github.com/rasuvaeff/understudy) | The engine: doubles, matchers, expectations, verification. |
| [rasuvaeff/understudy-testo](https://github.com/rasuvaeff/understudy-testo) | Testo adapter — verification and reset around every test. |
| [rasuvaeff/understudy-phpunit](https://github.com/rasuvaeff/understudy-phpunit) | PHPUnit and Pest adapter — the same, through a trait. |
| **rasuvaeff/understudy-psalm** *(this package)* | Psalm plugin — matcher-aware specifications and misuse diagnostics. |
| [rasuvaeff/understudy-phpstan](https://github.com/rasuvaeff/understudy-phpstan) | PHPStan extension — the same for PHPStan, plus its own rules. |

## Development

```bash
make build          # validate + cs + psalm + unit + integration
make test-integration
```

The integration suite runs real Psalm processes over a fixture project, twice:
once with the plugin and once without. The run without it is what proves the
run with it means anything — a plugin that loads and does nothing passes a
positive fixture exactly as well as one that works.

## License

BSD-3-Clause.
