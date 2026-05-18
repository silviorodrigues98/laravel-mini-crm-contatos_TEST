---
phase: 3
slug: events-broadcasting-polish
status: verified
nyquist_compliant: true
wave_0_complete: true
created: 2026-05-18
updated: 2026-05-18
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12.5.12 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --filter="ContactScoreProcessed\|LogContactScoreProcessed\|ObserverTest\|ScoreProcessingTest"` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~4 seconds |
| **Suite status** | 40 tests, 100 assertions, all green |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter="ContactScoreProcessed\|LogContactScoreProcessed\|ObserverTest\|ScoreProcessingTest"`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 4 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 03-01-01 | 01 | 1 | EVENT-01, EVENT-02, EVENT-03 | T-03-01 / T-03-02 | N/A — infrastructure task (Reverb install, .env config, log channel) | infra | Plan automated verify: `grep -q` checks + `php -l` | ✅ config/reverb.php, config/broadcasting.php, config/logging.php | ✅ green |
| 03-01-02 | 01 | 1 | EVENT-01, EVENT-02, EVENT-03 | T-03-01 / T-03-02 | Event carries domain data (no user input); Log writes to contact.log only | unit + feature | `php artisan test --filter="ContactScoreProcessedTest\|LogContactScoreProcessedTest\|test_score_processing_dispatches_contact_score_processed_event"` | ✅ ContactScoreProcessedTest.php, LogContactScoreProcessedTest.php, ScoreProcessingTest.php | ✅ green |
| 03-01-03 | 01 | 1 | EVENT-03 | T-03-01 | Public channel — no auth bypass risk (same data as API) | unit | `php artisan test --filter=test_event_broadcasts_on_correct_channel` | ✅ ContactScoreProcessedTest.php | ✅ green |
| 03-01-04 | 01 | 1 | EVENT-04 | — / — | N/A — documentation only | code review | Manual — verify README.md content | ✅ README.md | ✅ green |
| 03-02-01 | 02 | 1 | ARCH-08 | T-03-02 / — | Phone normalization prevents malformed data storage | feature | `php artisan test --filter=ObserverTest` | ✅ ObserverTest.php | ✅ green |
| 03-02-02 | 02 | 1 | TEST-06 | — / — | N/A — full suite validation | integration | `php artisan test` | ✅ Full suite | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Unit/Infrastructure/Events/ContactScoreProcessedTest.php` — test event dispatch + broadcast channel (EVENT-01, EVENT-03) — **42 lines, 2 tests**
- [x] `tests/Unit/Infrastructure/Listeners/LogContactScoreProcessedTest.php` — test log output (EVENT-02) — **41 lines, 1 test**
- [x] `tests/Feature/ObserverTest.php` — test phone normalization (ARCH-08) — **36 lines, 2 tests**
- [x] `tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php` — add assertion for return type change — **3 tests**

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| README HTML/JS snippet | EVENT-04 | HTML/JS isn't covered by `php artisan test` | Verify README.md contains `laravel-echo` CDN snippet subscribing to `contacts.{id}` channel |
| Reverb server starts | EVENT-03 | Server process, not testable via PHPUnit | Run `php artisan reverb:start` and confirm it listens on configured port |

*All other phase behaviors have automated verification.*

---

## Validation Audit 2026-05-18

| Metric | Count |
|--------|-------|
| Gaps found | 0 |
| Resolved | 0 |
| Escalated | 0 |

Full suite: 40 tests, 100 assertions, all green.
No gaps found — all phase requirements have automated or manual-only verification.

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 4s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** verified 2026-05-18
