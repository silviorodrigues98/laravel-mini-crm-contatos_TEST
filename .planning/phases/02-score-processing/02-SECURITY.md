---
phase: 02
slug: score-processing
status: verified
threats_open: 0
asvs_level: 1
created: 2026-05-18
---

# Phase 02 — Score Processing Security

> Per-phase security contract: threat register, accepted risks, and audit trail.

---

## Trust Boundaries

| Boundary | Description | Data Crossing |
|----------|-------------|---------------|
| HTTP → Controller | Client sends POST request to process-score endpoint — only path parameter is contact ID (no body) | int contactId (path param) |
| Controller → Job | Job dispatch passes only int $contactId — no user-controllable data enters the job payload | int contactId (serialized in queue) |
| Job → UseCase | Job receives use case via container method injection — no deserialization risks | In-memory method call |
| UseCase → Repository | Domain service calls infrastructure repository — all data is domain-controlled | Domain entity (Contact) |
| Calculator → Strategies | Domain-internal boundary — framework-agnostic, no user input reaches strategies directly | In-memory method call |

---

## Threat Register

| Threat ID | Category | Component | Disposition | Mitigation | Status |
|-----------|----------|-----------|-------------|------------|--------|
| T-02-01 | Tampering | ProcessScoreUseCase | mitigate | `markAsProcessing()`/`markAsActive()`/`markAsFailed()` call `assertTransition()` which validates every status change. Cannot skip states. | closed |
| T-02-02 | Elevation of Privilege | Contact entity status machine | mitigate | `ContactStatus::canTransitionTo()` enforces valid paths: pending→processing, processing→active\|failed. Terminal states reject all transitions. | closed |
| T-02-03 | Denial of Service | ScoreCalculator | accept | Calculator iterates strategies linearly. Each strategy is pure computation (no I/O). Negligible DoS surface from this layer. | closed |
| T-02-04 | Tampering | EmailDomainScoringStrategy | mitigate | Strategy operates on domain VOs (`Email::domain()`, `Email::tld()`) — already validated at entity creation. No raw email string reaches the strategy. | closed |
| T-02-05 | Tampering | ProcessContactScoreJob serialization | mitigate | Job only stores `int $contactId` — no user-controllable fields. `SerializesModels` trait unused (no Eloquent model serialized). | closed |
| T-02-06 | Denial of Service | ProcessContactScoreJob retries | mitigate | Job sets `$tries = 3` with `$maxExceptions = 2` and exponential `backoff()` ([2, 5, 15]s) — prevents infinite retry loops on domain exceptions. Plan specified `$tries = 1`; implementation uses bounded retries for resilience. | closed |
| T-02-07 | Tampering | Route parameter | mitigate | Route uses `{id}`, controller accepts `int $id` via PHP type hint, use case casts to `int $contactId`. No injection possible through a numeric path segment. | closed |
| T-02-08 | Elevation of Privilege | Status machine bypass (reprocessing active contact) | mitigate | Controller dispatches job; job calls `ProcessScoreUseCase::execute()` → `Contact::markAsProcessing()` → `assertTransition(Processing)`. Already-active contact throws `DomainException` — job fails safely after max retries. | closed |
| T-02-09 | Spoofing | No authentication on endpoint | accept | No auth required per spec. All endpoints public. If auth is added later, it applies uniformly. | closed |
| T-02-SC-01 | Tampering | Supply chain (Plan 01) | accept | No external package installs in domain layer plan. Pure PHP domain logic. | closed |
| T-02-SC-02 | Tampering | Supply chain (Plan 02) | accept | No external packages installed in infrastructure wiring plan. Only new PHP files added. | closed |

*Status: closed · open*
*Disposition: mitigate (implementation required) · accept (documented risk) · transfer (third-party)*

---

## Accepted Risks Log

| Risk ID | Threat Ref | Rationale | Accepted By | Date |
|---------|------------|-----------|-------------|------|
| AR-02-01 | T-02-03 | ScoreCalculator performs pure in-memory computation only. DoS from this layer is negligible — attack surface is the HTTP endpoint rate, not the calculation itself. | Plan 02-01 | 2026-05-18 |
| AR-02-02 | T-02-09 | All endpoints are intentionally public per spec. Adding auth was explicitly scoped out. No sensitive data exposed through score processing. | Plan 02-02 | 2026-05-18 |
| AR-02-03 | T-02-SC-01 | No third-party dependencies added in domain layer plan. Accepting by nature of the plan scope — no supply chain risk introduced. | Plan 02-01 | 2026-05-18 |
| AR-02-04 | T-02-SC-02 | No third-party dependencies added in infrastructure wiring plan. Accepting by nature of the plan scope — no supply chain risk introduced. | Plan 02-02 | 2026-05-18 |

*Accepted risks do not resurface in future audit runs.*

---

## Security Audit Trail

| Audit Date | Threats Total | Closed | Open | Run By |
|------------|---------------|--------|------|--------|
| 2026-05-18 | 11 | 11 | 0 | gsd-security-auditor (opencode/big-pickle) |

---

## Deviations Noted

| Threat ID | Plan Specified | Implementation | Impact |
|-----------|----------------|----------------|--------|
| T-02-06 | `$tries = 1` — single attempt, no retry | `$tries = 3`, `$maxExceptions = 2`, `backoff([2, 5, 15])` — bounded retries with exponential backoff | Mitigation intent preserved and strengthened. Bounded retries prevent infinite loops while allowing recovery from transient failures. |

---

## Sign-Off

- [x] All threats have a disposition (mitigate / accept / transfer)
- [x] Accepted risks documented in Accepted Risks Log
- [x] `threats_open: 0` confirmed
- [x] `status: verified` set in frontmatter

**Approval:** verified 2026-05-18
