# rasuvaeff/understudy-psalm

Psalm plugin for [understudy](https://github.com/rasuvaeff/understudy).

> Using an AI coding assistant? Point it at [llms.txt](llms.txt).

**Pre-release.** The diagnostics are not stable until v0.1.0.

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
| `$repo->find(Arg::int())` — a real call | still an error |

The last row is the point. A matcher reaching a real call raises
`MatcherLeaked` at runtime, and a plugin that hid it would be worse than no
plugin at all.

## API

| Type | Purpose |
|---|---|
| `UnderstudyMisuse` | The issue every diagnostic of this plugin is reported as. One type rather than one per rule: you either have understudy analysing your specifications or you do not, and needing to silence each rule separately would be a worse contract than needing to silence none. |
| `Plugin` | The entry point Psalm loads, registered through `extra.psalm.pluginClass`. Nothing else in this package is public — the handlers it registers are `@internal`, and what they decide is the plugin's behaviour, not its API. |

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
