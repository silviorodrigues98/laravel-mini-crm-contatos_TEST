# Requirements: Mini CRM de Contatos

**Defined:** 2026-05-12
**Core Value:** Contacts are created and their scores are calculated asynchronously with real-time status updates broadcast to the client.

## v1 Requirements

### Contacts CRUD

- [ ] **CONT-01**: User can create a contact with name, email, and phone (status defaults to `pending`, score to 0)
- [ ] **CONT-02**: User can list contacts with pagination
- [ ] **CONT-03**: User can view a single contact by ID
- [ ] **CONT-04**: User can update a contact's name, email, and phone
- [ ] **CONT-05**: User can soft-delete a contact

### Score Processing

- [ ] **SCORE-01**: User can trigger score processing via `POST /api/contacts/{id}/process-score`
- [ ] **SCORE-02**: Triggering enqueues an async job that sets status to `processing`
- [ ] **SCORE-03**: Score is calculated using Strategy pattern rules (email domain, name length, phone DDD)
- [ ] **SCORE-04**: On success, status becomes `active` with calculated score and `processed_at` timestamp
- [ ] **SCORE-05**: On failure, status becomes `failed` with score unchanged
- [ ] **SCORE-06**: Job simulates processing delay with `sleep(1-2)`

### Score Calculation Rules

- [ ] **CALC-01**: Corporate email domains (not gmail, hotmail, yahoo) score +20 points
- [ ] **CALC-02**: Emails with `.br` TLD score +10 points
- [ ] **CALC-03**: Full names (more than one word) score +10 points
- [ ] **CALC-04**: Phone with São Paulo DDD (11-19) scores +20 points
- [ ] **CALC-05**: Phone with other state DDD scores +10 points

### Domain Events & Broadcasting

- [ ] **EVENT-01**: `ContactScoreProcessed` domain event is dispatched after score calculation
- [ ] **EVENT-02**: Listener logs to `storage/logs/contact.log` (ID, email, score, status)
- [ ] **EVENT-03**: Listener broadcasts via Reverb on `contacts.{id}` channel
- [ ] **EVENT-04**: README includes basic HTML/JS example for listening to the channel

### Architecture & DDD

- [ ] **ARCH-01**: Domain layer contains entities, value objects, domain services (framework-agnostic)
- [ ] **ARCH-02**: Value Objects for Email, Phone, and Status (not raw strings)
- [ ] **ARCH-03**: Application layer contains use cases/actions orchestrating operations
- [ ] **ARCH-04**: Repository interfaces in Domain, Eloquent implementations in Infrastructure
- [ ] **ARCH-05**: Dependencies wired via Laravel service container
- [ ] **ARCH-06**: Form Requests for input validation
- [ ] **ARCH-07**: API Resources for standardized JSON output
- [ ] **ARCH-08**: Observer on Contact model (`saving` to normalize phone format)
- [ ] **ARCH-09**: Soft deletes, timestamps, `processed_at` on Contact model
- [ ] **ARCH-10**: Contact status enum (`pending`, `processing`, `active`, `failed`)

### Testing

- [ ] **TEST-01**: Unit tests for Domain entities and value objects
- [x] **TEST-02**: Unit tests for Application use cases (mocking infrastructure) — RED phase complete
- [x] **TEST-03**: Unit tests for score calculation strategies — RED phase complete
- [ ] **TEST-04**: Feature/integration tests for CRUD endpoints
- [ ] **TEST-05**: Feature tests for score processing flow (including queue)
- [ ] **TEST-06**: Full suite runs via `php artisan test`

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
| CONT-01 | Phase 1 | Pending |
| CONT-02 | Phase 1 | Pending |
| CONT-03 | Phase 1 | Pending |
| CONT-04 | Phase 1 | Pending |
| CONT-05 | Phase 1 | Pending |
| SCORE-01 | Phase 2 | Pending |
| SCORE-02 | Phase 2 | Pending |
| SCORE-03 | Phase 2 | Pending |
| SCORE-04 | Phase 2 | Pending |
| SCORE-05 | Phase 2 | Pending |
| SCORE-06 | Phase 2 | Pending |
| CALC-01 | Phase 2 | Pending |
| CALC-02 | Phase 2 | Pending |
| CALC-03 | Phase 2 | Pending |
| CALC-04 | Phase 2 | Pending |
| CALC-05 | Phase 2 | Pending |
| EVENT-01 | Phase 3 | Pending |
| EVENT-02 | Phase 3 | Pending |
| EVENT-03 | Phase 3 | Pending |
| EVENT-04 | Phase 3 | Pending |
| ARCH-01 | Phase 1 | Pending |
| ARCH-02 | Phase 1 | Pending |
| ARCH-03 | Phase 1 | Pending |
| ARCH-04 | Phase 1 | Pending |
| ARCH-05 | Phase 1 | Pending |
| ARCH-06 | Phase 1 | Pending |
| ARCH-07 | Phase 1 | Pending |
| ARCH-08 | Phase 1 | Pending |
| ARCH-09 | Phase 1 | Pending |
| ARCH-10 | Phase 1 | Pending |
| TEST-01 | Phase 2 | In Progress (tests written, implementation pending) |
| TEST-02 | Phase 2 | In Progress (tests written, implementation pending) |
| TEST-03 | Phase 2 | In Progress (tests written, implementation pending) |
| TEST-04 | Phase 1 | Pending |
| TEST-05 | Phase 2 | Pending |
| TEST-06 | Phase 3 | Pending |

**Coverage:**
- v1 requirements: 35 total
- Mapped to phases: 35
- Unmapped: 0 ✓

---
*Requirements defined: 2026-05-12*
*Last updated: 2026-05-12 after initial definition*
