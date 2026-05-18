---
phase: 02-score-processing
plan: 01
subsystem: scoring
tags: [tdd, phpunit, strategy-pattern, red-green, green-phase, domain-logic]
requires:
  - phase: 01-domain-foundation-crud
    provides: Contact entity, Email/Phone/Score VOs, ContactStatus enum, repository interface
provides:
  - Unit test suite for scoring strategies, calculator, and use case
  - ScoreScoringStrategy interface
  - EmailDomainScoringStrategy (corporate +20, .br TLD +10)
  - NameLengthScoringStrategy (multi-word +10)
  - PhoneDddScoringStrategy (SP DDD 11-19 +20, other DDD 20+ +10)
  - ScoreCalculator (aggregates strategies into Score VO)
  - ProcessScoreUseCase (pending→processing→active|failed orchestration)
affects: [02-02-PLAN.md, infrastructure wiring]

tech-stack:
  added: []
  patterns:
    - Pure PHPUnit unit tests (no Laravel bootstrap) for domain/application layers
    - Strategy pattern testing with mocks
    - TDD RED/GREEN cycle
    - Strategy pattern for scoring rules (open/closed principle)
    - Use case with status machine orchestration

key-files:
  created:
    - tests/Unit/Domain/Services/Scoring/EmailDomainScoringStrategyTest.php
    - tests/Unit/Domain/Services/Scoring/NameLengthScoringStrategyTest.php
    - tests/Unit/Domain/Services/Scoring/PhoneDddScoringStrategyTest.php
    - tests/Unit/Domain/Services/ScoreCalculatorTest.php
    - tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php
    - src/Domain/Services/Scoring/ScoreScoringStrategy.php
    - src/Domain/Services/Scoring/EmailDomainScoringStrategy.php
    - src/Domain/Services/Scoring/NameLengthScoringStrategy.php
    - src/Domain/Services/Scoring/PhoneDddScoringStrategy.php
    - src/Domain/Services/ScoreCalculator.php
    - src/Application/UseCases/ProcessScoreUseCase.php
  modified: []

key-decisions:
  - "Pure PHPUnit\Framework\TestCase used for domain/application tests (no Laravel bootstrap) to keep tests fast and framework-agnostic"
  - "Mock interfaces for ScoreCalculatorTest (ScoreScoringStrategy) and ProcessScoreUseCaseTest (ContactRepositoryInterface, ScoreCalculator) for isolated testing"
  - "Phone DDD boundary at 10 is invalid (Brazilian DDDs start at 11), so DDD < 11 returns 0 points"
  - "Phone DDD 20 is other state (not SP), returns 10 points"
  - "ScoreCalculator is NOT final (removed final keyword) to allow PHPUnit createMock() doubling — test was committed in RED phase and expects a mockable class"

patterns-established:
  - "Strategy tests: setUp() instantiates the strategy, each test creates a Contact with appropriate VOs and calls score()"
  - "Calculator test: uses createMock(ScoreScoringStrategy::class) to provide predictable return values"
  - "Use case test: mocks both repository and calculator, verifies Contact entity state post-execution"
  - "Domain strategies: final classes implementing a common interface, pure computation, no I/O"
  - "ProcessScoreUseCase: findById → markAsProcessing → save → calculate → markAsActive|Failed → save"

requirements-completed:
  - TEST-01
  - TEST-02
  - TEST-03
  - CALC-01
  - CALC-02
  - CALC-03
  - CALC-04
  - CALC-05
  - SCORE-03

metrics:
  duration: 18min
  completed: 2026-05-18
---

# Phase 02 Plan 01: Score Processing — TDD RED/GREEN Complete Summary

**All 17 tests pass: 3 scoring strategies, 1 calculator, and 1 use case implemented via Strategy pattern — RED→GREEN cycle complete**

## Performance

- **Duration:** 18 min total (12min RED + 6min GREEN)
- **Started:** 2026-05-18T14:20:00Z
- **Completed:** 2026-05-18T15:20:00Z
- **Tasks:** 2 (RED + GREEN)
- **Files modified:** 11 (5 test + 6 source)

## Accomplishments

### RED Phase (Task 1)
- Created 5 test files with 17 test methods covering all scoring rules
- **EmailDomainScoringStrategyTest** (5 tests): corporate email +20, .br TLD +10, gmail/hotmail 0, gmail.com.br only .br bonus
- **NameLengthScoringStrategyTest** (3 tests): multi-word +10, single word 0, 3+ words +10
- **PhoneDddScoringStrategyTest** (5 tests): SP DDD 11–19 +20, DDD 20+ other state +10, DDD < 11 invalid 0
- **ScoreCalculatorTest** (2 tests): sums strategies correctly, empty list returns Score(0)
- **ProcessScoreUseCaseTest** (2 tests): happy path → Active with score, exception path → Failed
- All 17 tests correctly failed with `ERROR` (missing implementation classes) — RED phase confirmed

### GREEN Phase (Task 2)
- **ScoreScoringStrategy interface** — single `score(Contact $contact): int` contract for all scoring strategies
- **EmailDomainScoringStrategy** — extracts domain prefix, adds 20 for corporate domains (not gmail/hotmail/yahoo), adds 10 for .br TLD
- **NameLengthScoringStrategy** — checks `str_word_count($contact->name()) >= 2`, returns 10 for multi-word names
- **PhoneDddScoringStrategy** — casts `ddd()` to int: 11-19 returns 20, >= 20 returns 10, otherwise 0
- **ScoreCalculator** — iterates all strategies, sums scores, returns `new Score($total)`
- **ProcessScoreUseCase** — orchestrates: findById → markAsProcessing → save → calculate → markAsActive|Failed → save (second save)
- **All 17 tests pass** — 25 assertions, no errors, no failures

## Task Commits

Each task was committed atomically:

1. **Task 1: Write failing unit tests (RED phase)** - `fc365fa` (test)
2. **Task 2: Implement scoring strategies, calculator, and use case** - `58b4426` (feat)

## Files Created/Modified

### Test Files (RED phase)
- `tests/Unit/Domain/Services/Scoring/EmailDomainScoringStrategyTest.php` — 5 tests
- `tests/Unit/Domain/Services/Scoring/NameLengthScoringStrategyTest.php` — 3 tests
- `tests/Unit/Domain/Services/Scoring/PhoneDddScoringStrategyTest.php` — 5 tests
- `tests/Unit/Domain/Services/ScoreCalculatorTest.php` — 2 tests
- `tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php` — 2 tests

### Source Files (GREEN phase)
- `src/Domain/Services/Scoring/ScoreScoringStrategy.php` — Strategy interface
- `src/Domain/Services/Scoring/EmailDomainScoringStrategy.php` — Corporate email + .br TLD rules
- `src/Domain/Services/Scoring/NameLengthScoringStrategy.php` — Multi-word name rule
- `src/Domain/Services/Scoring/PhoneDddScoringStrategy.php` — Brazilian DDD scoring rules
- `src/Domain/Services/ScoreCalculator.php` — Strategy aggregator
- `src/Application/UseCases/ProcessScoreUseCase.php` — Status machine orchestration

## Decisions Made

- Pure `PHPUnit\Framework\TestCase` (not Laravel's TestCase) used for all tests — keeps domain/application tests framework-agnostic and fast
- `ScoreCalculatorTest` uses `createMock(ScoreScoringStrategy::class)` to test calculator in isolation
- `ProcessScoreUseCaseTest` uses mocks for both `ContactRepositoryInterface` and `ScoreCalculator`, verifying Contact entity state after execution rather than asserting on mock interactions
- DDD boundary logic: DDD 10 is invalid (Brazilian DDDs start at 11), returns 0; DDD 20+ is other state, returns 10
- `ScoreCalculator` is NOT `final` — test uses `createMock(ScoreCalculator::class)` which cannot double a final class

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Removed `final` keyword from ScoreCalculator**
- **Found during:** Task 2 (GREEN phase)
- **Issue:** `ProcessScoreUseCaseTest` uses `$this->createMock(ScoreCalculator::class)` which PHPUnit cannot perform on a `final` class
- **Fix:** Changed `final class ScoreCalculator` to `class ScoreCalculator`
- **Files modified:** `src/Domain/Services/ScoreCalculator.php`
- **Commit:** `58b4426`
- **Rationale:** The TDD RED phase committed the test first. The test uses `createMock()` to isolate the use case from the calculator. Making ScoreCalculator not final is the minimal change to maintain compatibility. All behavior is unchanged — the class still provides the same API and guarantees.

## Issues Encountered

- PHPUnit cannot double `final` classes via `createMock()` — resolved by removing `final` from `ScoreCalculator` (Rule 3 deviation, documented above).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Scoring layer fully implemented and tested at domain/application level
- Next step: proceed to 02-02-PLAN.md for infrastructure wiring (queue job, endpoint, service provider bindings)

## Self-Check: PASSED

- `fc365fa` — test(02-01): add failing unit tests for scoring strategies, calculator, and use case ✓
- `58b4426` — feat(02-01): implement scoring strategies, calculator, and use case ✓
- `9e948e9` — docs(02-01): add RED phase summary, update state/roadmap/requirements ✓
- All 5 test files exist ✓
- All 6 source files exist ✓
- All tests pass ✓ (17 tests, 25 assertions)

---

*Phase: 02-score-processing*
*Completed: 2026-05-18*
