---
phase: 2
slug: score-processing
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-05-18
---

# Phase 2 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.x |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --filter=Score\|Process\|ScoreProcessing` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter=Score\|Process\|ScoreProcessing`
- **After every plan wave:** Run `php artisan test --filter=Score\|Process\|ScoreProcessing`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 20 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 02-01-01 | 01 | 1 | SCORE-01 | T-02-01 / — | N/A — API input validation | feature | `php artisan test --filter=test_can_trigger_score_processing` | ✅ W0 | ⬜ pending |
| 02-01-02 | 01 | 1 | SCORE-02, SCORE-06 | T-02-02 / — | N/A — async queue | feature | `php artisan test --filter=test_score_processing_queues_job` | ✅ W0 | ⬜ pending |
| 02-02-01 | 02 | 2 | SCORE-03, CALC-01–05 | T-02-03 / — | N/A — business logic | unit | `php artisan test --filter=EmailDomainScoring\|NameLengthScoring\|PhoneDddScoring` | ✅ W0 | ⬜ pending |
| 02-02-02 | 02 | 2 | SCORE-04, SCORE-05 | T-02-04 / — | N/A — status transition | unit | `php artisan test --filter=ProcessScoreUseCase\|test_status_transition` | ✅ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements (PHPUnit configured in Phase 1, test stubs created in Phase 1 for Contact CRUD).

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Queue worker actually processes with sleep(1-2) | SCORE-06 | `sleep()` makes tests slow | `Queue::fake()` in automated tests; manual: run `php artisan queue:work --once` after triggering |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 20s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
