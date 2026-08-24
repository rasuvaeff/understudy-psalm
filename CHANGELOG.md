# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

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
- Public Psalm API only. `Codebase::methodExists()` and
  `Codebase::getMethodParams()` take a plain `Class::method` string, so
  `Psalm\Internal\MethodIdentifier` and `Methods::getStorage()` — which
  answer the same question and are marked internal — are not used.
