# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.4 — 2026-08-28

- Allow `rasuvaeff/understudy` `^0.4`: `Arg::rest()`, `Arg::captor()`,
  `Understudy::delegate()`, `Understudy::lean()` and rendered property hooks
  are all additive — the adapter needs no code change.

## 0.1.3 — 2026-08-27

- Allow `rasuvaeff/understudy` `^0.3` (the engine refuses colliding same-call
  `when()`/`expect()` registrations with `ConflictingExpectation` from 0.3.0;
  nothing in this adapter changes behaviour).

## 0.1.2 — 2026-08-27

- Accept `rasuvaeff/understudy` 0.2 alongside 0.1. Nothing in the adapter
  changes; the core's 0.2.0 is additive, and on 0.x Composer's caret treats a
  minor as a boundary, so the constraint has to say so explicitly. Widening it
  breaks no existing install.

## 0.1.1 — 2026-08-26

- **The release workflow waits for the matrix build instead of judging it
  mid-flight.** A tag pushed right after the merge arrived while master's own
  build was still running, and the guard read a `null` conclusion as a failed
  one, refusing to create the GitHub Release. Hit for real on the core package
  while tagging `v0.1.1`. No effect on the package itself.

- **Fixed: a matcher was recognised by its short class name.** Anybody's
  `Acme\Console\Arg` was claimed as ours — its static calls judged against the
  parameter they were passed to, and reported as a misuse of a package the
  project may not even use — while our own `Arg`, imported under another name,
  stopped being recognised at all. Both are decided by the resolved class now,
  as `understudy-phpstan` already did it.
- The text heuristic behind the suppression hook no longer accepts a foreign
  namespace in front of `Arg`. What it still cannot decide, because it is
  handed a source selection rather than a node, is an unqualified `Arg::` in a
  file that imported somebody else's class under that name — stated in the
  code and pinned by a fixture rather than left to be discovered.

## 0.1.0 — 2026-08-25

- `Understudy::lastCall()` is a specification verb. It was added to the core
  after this plugin was written, so its closure was outside every rule: no
  matcher suppression and no misuse diagnostics. A reader added to the core
  belongs in `VerbNames::STATIC_VERBS` the day it lands.
- Every closure argument of a specification is checked, not only the first.
  A wrong-kind matcher in the third step of a `verifySequence()` protocol was
  as silent as the first step was loud.
- The call-closure readers are specification scopes too: a matcher inside
  `Understudy::calls()` or `Understudy::verifySequence()` no longer reports as
  an argument-type error. Found by dogfooding the plugin over the migrated
  `yii3-correlation-id` suite — the matcher in a `calls()` closure was reported
  exactly like a leak. Both carry fixtures in the Matchers integration
  project; a matcher in a real call keeps being an error.
- Initial development. The feasibility gate the plan puts before every other
  rule (§7): a matcher standing in for a typed parameter inside a
  specification closure stops being an argument-type error, and the same
  matcher passed to a real call keeps being one. Proven by two real Psalm runs
  over one fixture project, with and without the plugin.
- Diagnoses specifications that cannot work, all reported as
  `UnderstudyMisuse`: a matcher whose kind the parameter can never accept, a
  closure that specifies nothing or specifies two calls, a closure calling a
  static method, cardinality bounds no run can satisfy, and `verify()`
  arguments that contradict each other. Each has a fixture, and each has a
  correct neighbour in a control file that must stay silent.
- `returns()` and `answers()` are checked against the method being specified.
  The core declares `when(): WhenBuilder<mixed>` and has no choice — the
  method is only known from the closure — so the plugin fills the template
  parameter in and Psalm does the checking itself, with its own
  `InvalidArgument`.
- `wire()` gives back a shape read from the named class's constructor: an
  unknown key is an error naming the real ones, and each double is typed as
  its contract. A dynamic class-string is left alone.
- Public Psalm API only. `Codebase::methodExists()` and
  `Codebase::getMethodParams()` take a plain `Class::method` string, so
  `Psalm\Internal\MethodIdentifier` and `Methods::getStorage()` — which
  answer the same question and are marked internal — are not used.
