# AGENTS.md — understudy-psalm

Guidance for AI agents working on this package. Read before changing code.

## What this is

The Psalm plugin of the understudy family. It teaches Psalm the one thing the
call-closure API cannot express in types: `Arg::*()` returns `mixed` so a
matcher can stand in for any declared parameter, and inside a specification
closure that is correct rather than an error.

Namespace `Rasuvaeff\Understudy\Psalm`. Entry point `Plugin`, registered
through `extra.psalm.pluginClass`.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **A positive fixture proves nothing on its own.** A plugin that loads and
   does nothing passes it exactly as well as one that works. Every diagnostic
   this plugin adds or removes needs a control run — the same files, no
   plugin — showing the outcome differs. That is why the integration suite
   carries `psalm-without-plugin.xml`.
4. **Preserve the public contract.** Update README + README.ru + tests with
   any change to what is reported.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
make install
make build
make test-integration
```

## Invariants & gotchas

- **Public hooks only.** `BeforeExpressionAnalysisInterface` records where a
  specification call is; `BeforeAddIssueInterface` decides about the issue.
  Psalm's internal API is off limits, and a diagnostic that cannot be built
  from public hooks is dropped from the plan rather than faked (plan §7).
- **Order is why there are two hooks.** Argument issues are raised WHILE the
  call is analysed, so anything that fires afterwards — `AfterFunctionCall`,
  `AfterExpression` — is too late to see them. The scope has to be recorded
  before.
- **Suppression is narrow by construction.** Argument-shaped issue, inside a
  recorded specification call, and the selected text is an `Arg::` matcher.
  Widening any of the three hides the mistakes the plugin exists to surface.
- **`SpecificationScope` holds static state** across a run. That is fine for
  Psalm, which analyses each file once, and `reset()` exists for tests that
  drive the handlers directly.
- **Every gate here reads the working tree; a consumer downloads
  `git archive`.** Between the two there is `export-ignore` and nothing else, so
  a file that should not ship reaches users without reddening anything. The
  `Consumer smoke` job installs a dist archive of the commit into a throwaway
  project, takes the engine from Packagist the way a user does, and drives the plugin from a consumer `psalm.xml`.
  The script itself lives in the core repository (`understudy/bin/consumer-smoke`) —
  one copy, checked out by this workflow; run the whole family from local
  checkouts with `bin/understudy-consumer-smoke` in the workspace repository.
- **CI workflows are SHA-pinned**; never revert to floating `@vN` tags.

## When you finish

- Update `README.md` **and `README.ru.md`**, and `CHANGELOG.md` when releasing.
- Re-run `composer build`, which includes the integration suite. Paste output.
