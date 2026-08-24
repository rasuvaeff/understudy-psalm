# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

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
