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
- `rasuvaeff/understudy` ^0.4 || ^0.5 || ^0.6 || ^0.7

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
| `expectSequence(fn () => ..., fn () => ...)` | silent |
| `$repo->find(Arg::int())` — a real call | still an error |

The last row is the point. A matcher reaching a real call raises
`MatcherLeaked` at runtime, and a plugin that hid it would be worse than no
plugin at all.

**What reports that last row is Psalm, not this plugin, and it needs
`errorLevel="1"`.** The plugin suppresses; the report it leaves standing is
Psalm's own `MixedArgument`, which exists only at level 1. At levels 2 and
above a leaked matcher draws nothing here — the runtime `MatcherLeaked` is
what catches it, one test run later. The PHPStan plugin has a rule of its own
(`understudy.matcherLeak`) and reports at every level; this one deliberately
does not, because a rule strict enough to catch a leak textually also
misreads a matcher that reaches its specification through a variable, a
property or a helper, and a false accusation costs more here than a missed
one.

Every call-closure verb counts, in either spelling: the free functions
`when()`, `expect()`, `expectSequence()` and `verify()`, the same names on
`Understudy::`, and the readers that exist only there — `calls()`,
`lastCall()` and `verifySequence()`. Every closure of a protocol is read, so
a matcher in its third step is treated exactly like one in a `when()`.

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

Which of these the plugin acts on is decided by the resolver, never by the
written name: somebody else's `Acme\Console\Arg::int()` inside a specification
keeps its own diagnostics, and ours imported under an alias loses them under
any name. That holds for `capture()` too, whose receiver Psalm has resolved by
the time it is asked for the method's return type — a foreign zero-argument
`capture()` written inside a specification is somebody else's method and keeps
everything Psalm has to say about it.

## What else it reports

| Reported as | For |
|---|---|
| `UnderstudyMisuse` | A matcher whose kind the parameter can never accept. A closure that specifies nothing, or two calls, or calls a static method. Cardinality no run can satisfy. `verify()` arguments that contradict each other. |
| Psalm's own `InvalidArgument` | `returns()` and `answers()` against the method being specified. The plugin does not check these — it fills in the builder's template parameter, and `WhenBuilder<TReturn>` already declares `returns(TReturn ...)`. Psalm does the rest. |
| Psalm's own array and method diagnostics | `wire()`, whose shape is read from the named class's constructor: an unknown key is an error, and each double is typed as its contract — an intersection parameter keeps its intersection, because the core builds one double for all of it. A class the core refuses to wire, and a dynamic class-string, are left alone. |

Everything the plugin is not sure about stays silent. A false accusation costs
more than a missed one here, because the engine still catches what static
analysis misses.

That is why a refined parameter type draws nothing: `Arg::string()` against a
`non-empty-string`, a `class-string` or a literal union is a pairing an
argument can satisfy, and so is `Arg::int()` against `positive-int` or
`int<1, 10>` — a refinement is still the kind it refines. The rule speaks only
when EVERY member of the parameter's type is a definite no, and it declines to
judge a type whose values it cannot enumerate.

## API

| Type | Purpose |
|---|---|
| `UnderstudyMisuse` | The issue every diagnostic of this plugin is reported as. One type rather than one per rule: you either have understudy analysing your specifications or you do not, and needing to silence each rule separately would be a worse contract than needing to silence none. |
| `Plugin` | The entry point Psalm loads, registered through `extra.psalm.pluginClass`. Nothing else in this package is public — the handlers it registers are `@internal`, and what they decide is the plugin's behaviour, not its API. |

Both are stable: they are the two names a consumer writes down — one in
`psalm.xml`, one in `issueHandlers` — and renaming either would silently stop a
suppression somebody wrote. The **wording** of a diagnostic is not stable and a
patch release may reword one; assert on the issue type, never on the sentence.

## Security

The plugin runs inside Psalm, reads source and reflection, and reports. It
executes no code from the project under analysis and writes nothing.

## Examples

See [examples/README.md](examples/README.md). The executable demonstration is
the set of fixture projects under `tests/Integration/Fixtures`, each analysed
by a real Psalm process as part of `composer build` — including a control run
with the plugin switched off, which is what tells a working plugin apart from
one that loads and does nothing.

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
make build          # validate, normalize, require-checker, cs, psalm, unit, integration
make test-integration
```

The integration suite runs real Psalm processes over a fixture project, twice:
once with the plugin and once without. The run without it is what proves the
run with it means anything — a plugin that loads and does nothing passes a
positive fixture exactly as well as one that works.

## License

BSD-3-Clause.
