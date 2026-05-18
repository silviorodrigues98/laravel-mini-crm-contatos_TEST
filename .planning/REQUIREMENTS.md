# Requirements: Mini CRM de Contatos

**Defined:** 2026-05-12
**Core Value:** Contacts are created and their scores are calculated asynchronously with real-time status updates broadcast to the client.

## v1 Requirements

### Contacts CRUD

- [x] **CONT-01**: User can create a contact with name, email, and phone (status defaults to `pending`, score to 0)
- [x] **CONT-02**: User can list contacts with pagination
- [x] **CONT-03**: User can view a single contact by ID
- [x] **CONT-04**: User can update a contact's name, email, and phone
- [x] **CONT-05**: User can soft-delete a contact

### Score Processing

- [x] **SCORE-01**: User can trigger score processing via `POST /api/contacts/{id}/process-score`
- [x] **SCORE-02**: Triggering enqueues an async job that sets status to `processing`
- [x] **SCORE-03**: Score is calculated using Strategy pattern rules (email domain, name length, phone DDD)
- [x] **SCORE-04**: On success, status becomes `active` with calculated score and `processed_at` timestamp
- [x] **SCORE-05**: On failure, status becomes `failed` with score unchanged
- [x] **SCORE-06**: Job simulates processing delay with `sleep(1-2)`

### Score Calculation Rules

- [x] **CALC-01**: Corporate email domains (not gmail, hotmail, yahoo) score +20 points
- [x] **CALC-02**: Emails with `.br` TLD score +10 points
- [x] **CALC-03**: Full names (more than one word) score +10 points
- [x] **CALC-04**: Phone with São Paulo DDD (11-19) scores +20 points
- [x] **CALC-05**: Phone with other state DDD scores +10 points

### Domain Events & Broadcasting

- [x] **EVENT-01**: `ContactScoreProcessed` domain event is dispatched after score calculation
- [x] **EVENT-02**: Listener logs to `storage/logs/contact.log` (ID, email, score, status)
- [x] **EVENT-03**: Listener broadcasts via Reverb on `contacts.{id}` channel
- [x] **EVENT-04**: README includes basic HTML/JS example for listening to the channel

### Architecture & DDD

- [x] **ARCH-01**: Domain layer contains entities, value objects, domain services (framework-agnostic)
- [x] **ARCH-02**: Value Objects for Email, Phone, and Status (not raw strings)
- [x] **ARCH-03**: Application layer contains use cases/actions orchestrating operations
- [x] **ARCH-04**: Repository interfaces in Domain, Eloquent implementations in Infrastructure
- [x] **ARCH-05**: Dependencies wired via Laravel service container
- [x] **ARCH-06**: Form Requests for input validation
- [x] **ARCH-07**: API Resources for standardized JSON output
- [x] **ARCH-08**: Observer on Contact model (`saving` to normalize phone format)
- [x] **ARCH-09**: Soft deletes, timestamps, `processed_at` on Contact model
- [x] **ARCH-10**: Contact status enum (`pending`, `processing`, `active`, `failed`)

### Testing

- [x] **TEST-01**: Unit tests for Domain entities and value objects
- [x] **TEST-02**: Unit tests for Application use cases (mocking infrastructure)
- [x] **TEST-03**: Unit tests for score calculation strategies
- [x] **TEST-04**: Feature/integration tests for CRUD endpoints
- [x] **TEST-05**: Feature tests for score processing flow (including queue)
- [x] **TEST-06**: Full suite runs via `php artisan test`

## v2 Requirements

### Notifications

- **NOTF-01**: Email notification when score processing completes

### Advanced Scoring

- **ADV-01**: Additional scoring strategies (social media presence, etc.)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Authentication / user management | Not required by spec |
| Frontend SPA | Only HTML/JS example for Reverb |
| Persistent WebSocket reconnect | Not required by spec |
| Admin dashboard | Not required by spec |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| CONT-01 | Phase 1 | Completed |
| CONT-02 | Phase 1 | Completed |
| CONT-03 | Phase 1 | Completed |
| CONT-04 | Phase 1 | Completed |
| CONT-05 | Phase 1 | Completed |
| SCORE-01 | Phase 2 | Completed |
| SCORE-02 | Phase 2 | Completed |
| SCORE-03 | Phase 2 | Completed |
| SCORE-04 | Phase 2 | Completed |
| SCORE-05 | Phase 2 | Completed |
| SCORE-06 | Phase 2 | Completed |
| CALC-01 | Phase 2 | Completed |
| CALC-02 | Phase 2 | Completed |
| CALC-03 | Phase 2 | Completed |
| CALC-04 | Phase 2 | Completed |
| CALC-05 | Phase 2 | Completed |
| EVENT-01 | Phase 3 | Completed |
| EVENT-02 | Phase 3 | Completed |
| EVENT-03 | Phase 3 | Completed |
| EVENT-04 | Phase 3 | Completed |
| ARCH-01 | Phase 1 | Completed |
| ARCH-02 | Phase 1 | Completed |
| ARCH-03 | Phase 1 | Completed |
| ARCH-04 | Phase 1 | Completed |
| ARCH-05 | Phase 1 | Completed |
| ARCH-06 | Phase 1 | Completed |
| ARCH-07 | Phase 1 | Completed |
| ARCH-08 | Phase 1 | Completed |
| ARCH-09 | Phase 1 | Completed |
| ARCH-10 | Phase 1 | Completed |
| TEST-01 | Phase 2 | Completed |
| TEST-02 | Phase 2 | Completed |
| TEST-03 | Phase 2 | Completed |
| TEST-04 | Phase 1 | Completed |
| TEST-05 | Phase 2 | Completed |
| TEST-06 | Phase 3 | Completed |

**Coverage:**
- v1 requirements: 35 total
- Mapped to phases: 35
- Unmapped: 0 ✓

---
*Requirements defined: 2026-05-12*
*Last updated: 2026-05-18 after milestone v1.0 audit*
