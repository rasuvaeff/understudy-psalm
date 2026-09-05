# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.8.2 — 2026-09-05

- Guards the plugin's load list with a regression test and completes the
  plugin's public summary in the generated family reference.
- Removes the stale 1.0 wording from `llms.txt` while the engine family remains
  on its 0.x compatibility line.

## 0.8.1 — 2026-09-05

- Allows `rasuvaeff/understudy` `^0.8 || ^0.9`. A bridge, not a widening for
  its own sake: the engine's 0.9 is the 1.0 candidate — every contract decision
  of the 1.0 review lands there, and 1.0 follows once it has been driven by real
  packages — and a project taking it must be able to keep this plugin without a
  window in which `composer require` silently installs the 0.8 engine beside
  it. The `^0.8` term is dropped in the release that follows the engine's.
- Rector is green again, which it had not been since 0.5.0: `rector.php`
  skips the fixture projects — they are the test input, and
  `SortCallLikeNamedArgsRector` would have rewritten the `Misuse` fixture
  that pins named bounds in either order into the order that never showed the
  bug — and the remaining suggestions are applied. `composer release-check`
  is what gates a tag, and it had been red under green builds.
- `infection/infection` moves to `^0.35`, the monorepo's single-major form.
- Documentation no longer dates the `Arg::rest()` and `Arg::captor()` idioms to
  «understudy 0.4»: with a floor of `^0.8` every engine this plugin installs
  beside has them. `AGENTS.md` no longer cites a section of the retired plan.

## 0.8.0 — 2026-09-05

- Requires `rasuvaeff/understudy` `^0.8`, and requires it as a single term. The
  accumulating union it carried (`^0.4 || ^0.5 || …`) had to be widened by hand
  on every core release, and a package that misses one becomes uninstallable
  beside its own engine.
- **The plugin no longer crashes Psalm on `foo(...)`.** A first-class callable
  carries a `VariadicPlaceholder` where its arguments would be, and php-parser
  asserts against reading them; the `before` hook read them on every call it
  saw, so a single `strlen(...)` anywhere in the analysed code took the whole
  run down — no diagnostics, on any file, at any level. That is PHP 8.1 syntax
  and this package declares 8.3–8.5.
- **`->returns(...)->times(5, 2)` is reported as impossible again.** The
  cardinality rule read only the immediate receiver of `times()`, so it fired
  on `expect(...)->times(5, 2)` and stayed silent on every spelling with a
  link in between — including the one the engine's own README recommends for a
  repeated call.
- **`times(maximum: 5, minimum: 1)` is no longer reported as impossible.** The
  bounds were read positionally, so a named call with the arguments in the
  other order looked like `(5, 1)`. Correct code turned red, with no way around
  it but removing the plugin.
- **A specification that reaches its double through a helper is silent.**
  `when(fn () => $this->gate()->find(1))`, `$double->find($this->id())` and
  `$this->passThrough($double->find(1))` were all reported as "the closure
  makes 2 calls". The engine throws on the first call that lands on a double
  and never sees the rest, so a call that is the receiver of another, or one
  made on `$this`, is not a second specified call. The total is still what
  answers "no call at all", so a specification that only reaches its double
  through a helper is not accused of specifying nothing either.
- A matcher whose kind cannot fit is described as matching "a value of type
  int" rather than "a int" — the wording `understudy-phpstan` already used.
- README ×2 and `llms.txt` now say that a leaked matcher is reported by Psalm's
  own `MixedArgument` and therefore only at `errorLevel="1"`, and why this
  plugin has no rule of its own for it.

## 0.7.0 — 2026-09-04

- **Matcher argument suppression is now scoped to real matcher calls.**
  `Arg::captor()` builds a captor and is no longer mistaken for a matcher, so
  Psalm keeps reporting an invalid captor value passed directly to a typed
  parameter.
- **`TooFewArguments` suppression now uses file offsets for every scope.** A
  valid `Arg::rest()` specification and a real under-arity call on the same
  source line are distinguished, so the real call keeps Psalm's diagnostics.
- Added control-run integration coverage for both boundaries.
- Calls returned from a specification closure are analysed (#31).

## 0.6.1 — 2026-09-04

- **Documentation review fixes.** Added the missing `make test-integration`
  target — README and AGENTS referenced it, the Makefile did not define it.
  Both READMEs gained the Security and Examples sections; the `make build`
  comment now lists everything the script runs.

## 0.6.0 — 2026-09-04

A minor rather than a patch, for the same reason 0.4.0 and 0.5.0 were: the
plugin reports something in the consumer's own code that it used to pass over.

- Allow `rasuvaeff/understudy` `^0.7`. Widened rather than raised.
- `verify($call, times: -1)` is reported. `verifyProblem()` fell through to
  the `minimum`/`maximum` pair and dropped `times` on the way, so a negative
  exact count reached no check at all. It has a unit test rather than a
  fixture on purpose: `verify()` declares `int<0, max>|null`, so wherever
  Psalm checks that annotation it reports the argument itself and this rule
  never speaks — what the rule buys is the levels where it does not.
- `Cardinality` and `ClosureShape` have unit tests, ported from the PHPStan
  sibling, which had them. Four of the eight internals were covered; six are
  now, and the two that are left need a `Codebase` to build.
- The `CaptureShapes` fixture is asserted. It was in the fixture project and
  in no assertion, so nothing said whether the plugin did anything about a
  capture in a nested position — which is the one thing the file exists to
  ask. Both directions are pinned now, control run included.
- `BuilderType` and `SpecificationRules` say why the receiver has to be a
  plain variable: `vars_in_scope` is keyed by variable name, so a double
  reached through `$this->repository` has no entry to look up.
- The `VerbNames` docblock no longer counts the verbs — it said "three", and
  there are four.

## 0.5.0 — 2026-09-04

A minor rather than a patch, for the same reason 0.3.0 and 0.4.0 were: the
plugin now reports diagnostics in the consumer's own code that it used to
swallow.

- Allow `rasuvaeff/understudy` `^0.6`. Widened rather than raised: the plugin
  works against 0.4, 0.5 and 0.6, and consumers on the older ones should not
  be cut off from it.

- **`wire()` typed a union-typed constructor parameter as one of its members**,
  so `$wired['doubles']['either']->now()` passed analysis for a call that
  always throws: the core refuses a union naming more than one object type
  (`CannotWire`), and which member the plugin picked was whichever the atomic
  map happened to hold first. No shape is produced for such a class now, and
  the core's own declaration stands. The intersection half — one double
  standing for every contract in it — keeps working and is now pinned by a
  fixture that calls a method of each half; it worked only because Psalm holds
  `A&B` as one atomic carrying the rest in `extra_types`, and nothing said so.
  Fixes #24.

- **`Arg::string()` no longer accuses a `non-empty-string` parameter.** The
  matcher-kind rule compared the PRINTED NAME of a parameter's type against
  the word `string` or `int`, so every refined type was a pairing "no argument
  can satisfy" — `non-empty-string`, `numeric-string`, `class-string`, a
  literal string or int, `positive-int`, `int<1, 10>`, and `callable`, which a
  string legitimately is. The branch meant to cover literals looked for
  `string(…)`, a spelling `Union::getId()` never produces, so it had never run.
  Measured on the fixture: six false reports on correct code, none now. The
  rule reads Psalm's atomic types instead — `TNonEmptyString` IS a `TString`,
  `TIntRange` IS a `TInt` — and stays silent for any atomic it does not read.
  Fixes #22.
- `MatcherKindTest` covers the rule directly: both directions of every kind,
  the refinements, the sets the rule declines to take apart, unions, and the
  matchers whose kind is not knowable from their name.
- The mutation gate rises from 85 to 97. Measured on PHP 8.4, the version the
  coverage job pins: 77 of 78 mutants killed, and the one survivor is
  equivalent (`ScopeIndex`'s `<=` mutated to `<`, where both branches build
  the same pair when the bounds are equal).
- `VerbNamesTest` gains the two cases its PHPStan sibling already had: a
  written leading separator, and a foreign namespace exactly as long as ours.
  Both were surviving mutants — without the second, the early `return false`
  for a foreign prefix was worth nothing, because falling through reads a verb
  out of a foreign name of the right length.
- `expectSequence()` is recognised as a specification verb. It was in neither
  spelling's list, so `SpecificationScope` never recorded the call: matchers
  inside an armed protocol lost their suppression and Psalm reported them like
  matchers anywhere else — seven reports on the fixture file that must be
  silent. The misuse rules were absent on the same closures, so a wrong-kind
  matcher in a protocol step drew nothing at all; it is now reported, which is
  a new diagnostic in consumer code. Fixes #20.
- `VerbNamesTest` now walks the core's own public surface and fails when a
  closure-taking verb is missing from either list. `expectSequence()` was the
  second verb to fall outside every rule after `lastCall()`; a list nobody
  checks is what let both happen.

## 0.4.0 — 2026-09-03

A minor rather than a patch, for the same reason 0.3.0 was: a diagnostic the
plugin used to swallow now appears in the consumer's own code.

- **A foreign `capture()` inside a specification keeps its diagnostic.** The
  last thing this plugin decided by shape was a captor's `capture()` — a method
  call with that name and no arguments — because `BeforeAddIssue` is handed no
  resolved receiver. Anybody else's zero-argument `capture()` written inside a
  specification was therefore taken for a captor's and lost whatever Psalm had
  to say about it. `CaptorRecorder` now records those calls from
  `MethodReturnTypeProvider`, where Psalm has already resolved the receiver and
  hands over the node, so the suppression hook answers by resolution
  throughout and the shape test is gone. The known limitation documented in
  both READMEs and `llms.txt` goes with it. (#18)
- Stability, stated rather than implied: the `UnderstudyMisuse` issue type and
  the `Plugin` entry point are what a consumer's `issueHandlers` and
  `psalm.xml` name, so they are stable; the wording of a diagnostic is not.

## 0.3.0 — 2026-09-03

A minor rather than a patch: the plugin now reports diagnostics on the
consumer's own code that it used to swallow, and stops reporting ones it
raised wrongly — a boundary Composer's caret already treats as breaking on
0.x.


- **The suppression hook asks the resolver instead of reading the source
  text.** It dropped an argument diagnostic when the reported selection *looked
  like* an `Arg::` call, which was wrong in both directions and both of them
  landed in the consumer's own code: somebody else's class named `Arg`, written
  unqualified inside a specification, lost a real type error, and our own `Arg`
  imported under an alias kept a `MixedArgument` the plugin exists to remove.
  `SpecificationScope` now records the file offsets of every call it resolved
  to `Rasuvaeff\Understudy\Arg`, and the hook answers from that. The internal
  `MatcherText` class is gone. `capture()` is still matched by shape — a
  zero-argument method call — because the receiver's type is not available
  where the report is intercepted, and that residue is documented rather than
  implied. (#15)
- The Requirements section of both READMEs said `rasuvaeff/understudy` `^0.1`
  while `composer.json` has required `^0.4` since 0.2.0.
- Allow `rasuvaeff/understudy` `^0.5`. Widened rather than raised: the plugin
  works against both, and the suppression fix above must reach consumers still
  on core 0.4.

## 0.2.0 — 2026-08-28

A minor rather than a patch: new behaviour toward the consumer's own code
(an ignored arity report, a newly typed matcher) and a raised dependency
floor are both boundaries Composer's caret already treats as breaking on 0.x.

- The `rasuvaeff/understudy` floor rises to `^0.4`: the fixtures that prove
  the new behaviour are written in the 0.4 idioms, and a lowest-versions run
  against 0.1 would be proving nothing. Consumers on an older understudy stay
  on the 0.1.x line of this package.
- **understudy 0.4 idioms** (rasuvaeff/understudy-psalm#11): `TooFewArguments`
  goes quiet on a call inside a specification whose last written argument is
  `Arg::rest()` — both conditions AST-resolved, so a real call ending in
  `Arg::rest()` keeps its arity report on top of the runtime
  `ArgumentCountError`. A captor's `$captor->capture()` is recognised as a
  matcher: its argument-type report is dropped inside a specification, it no
  longer counts against "exactly one call per closure", and a capture leaked
  into a real call is still reported.

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
