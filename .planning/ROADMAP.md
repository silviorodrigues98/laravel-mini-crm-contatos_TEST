# Roadmap: Mini CRM de Contatos

**3 phases** | **35 requirements mapped** | All v1 requirements covered ✓

| # | Phase | Goal | Requirements | Success Criteria |
|---|-------|------|--------------|------------------|
| 1 | Domain Foundation & CRUD | Implement core domain entities, VOs, repository interfaces, migrations, and full CRUD endpoints | CONT-01–05, ARCH-01–10, TEST-04 | 7 |
| 2 | Score Processing | Implement async score calculation with Strategy pattern, queue job, and processing trigger | SCORE-01–06, CALC-01–05, TEST-01–03, TEST-05 | 9 |
| 3 | Events, Broadcasting & Polish | Implement domain events, log listener, Reverb broadcast, HTML example, and final test suite wiring | EVENT-01–04, TEST-06 | 5 |

---

### Phase 1: Domain Foundation & CRUD
**Goal:** Define domain layer entities/value objects, repository contract, Laravel migration, Eloquent repository, CRUD endpoints with validation and API resources.
**Mode:** mvp
**Success Criteria:**
1. Contact entity exists with Email, Phone, Status value objects (no raw strings)
2. Repository interface in Domain, Eloquent implementation in Infrastructure, wired via container
3. `POST /api/contacts` creates a contact with status `pending` and score 0
4. `GET /api/contacts` returns paginated list
5. `GET /api/contacts/{id}` returns single contact
6. `PUT /api/contacts/{id}` updates contact fields
7. `DELETE /api/contacts/{id}` soft-deletes contact

### Phase 2: Score Processing
**Goal:** Implement score calculation strategies, async job, processing trigger endpoint, and status machine.
**Mode:** mvp
**Success Criteria:**
1. `POST /api/contacts/{id}/process-score` enqueues a job and returns 202
2. Job transitions contact status: `pending` → `processing` → `active|failed`
3. Corporate email domains score +20, `.br` TLD scores +10
4. Full names (multi-word) score +10
5. São Paulo DDD (11-19) scores +20, other DDD scores +10
6. Calculated score is persisted in `score` column
7. `processed_at` is set on completion
8. Strategy pattern allows easy addition of new scoring rules
9. Domain/application unit tests pass (infrastructure mocked)

### Phase 3: Events, Broadcasting & Polish
**Goal:** Wire domain events, log listener, Reverb broadcast, HTML/JS example, and finalize test suite.
**Mode:** mvp
**Success Criteria:**
1. `ContactScoreProcessed` event is dispatched after score calculation
2. Listener writes to `storage/logs/contact.log` with ID, email, score, status
3. Listener broadcasts via Reverb on `contacts.{id}` channel
4. README includes HTML/JS snippet for listening to the channel
5. Full `php artisan test` suite passes
6. Observer normalizes phone on `saving`

---

## Phase Details

### Phase 1: Domain Foundation & CRUD
**Goal:** Define domain layer entities/value objects, repository contract, Laravel migration, Eloquent repository, CRUD endpoints with validation and API resources.
**Mode:** mvp
**Requirements:** CONT-01, CONT-02, CONT-03, CONT-04, CONT-05, ARCH-01, ARCH-02, ARCH-03, ARCH-04, ARCH-05, ARCH-06, ARCH-07, ARCH-08, ARCH-09, ARCH-10, TEST-04
**Success criteria:**
1. Contact entity exists with Email, Phone, Status value objects (no raw strings)
2. Repository interface in Domain, Eloquent implementation in Infrastructure, wired via container
3. CRUD endpoints operational with validation and API resources
4. Soft deletes, timestamps working
5. Feature tests for all CRUD endpoints pass

**Plans:** 2 plans

Plans:
- [x] 01-01-PLAN.md — Walking Skeleton: Environment, Domain Layer, Create + List Contacts
- [x] 01-02-PLAN.md — Complete CRUD: Show, Update, Delete + Feature Tests

### Phase 2: Score Processing
**Goal:** Implement score calculation strategies, async job, processing trigger endpoint, and status machine.
**Mode:** mvp
**Requirements:** SCORE-01, SCORE-02, SCORE-03, SCORE-04, SCORE-05, SCORE-06, CALC-01, CALC-02, CALC-03, CALC-04, CALC-05, TEST-01, TEST-02, TEST-03, TEST-05
**Success criteria:**
1. Processing endpoint enqueues job
2. Correct status transitions
3. Score calculation correctness per rules
4. Strategy pattern extensibility
5. Unit tests for strategies and use cases pass

**Plans:** 2 plans

Plans:
- [ ] 02-01-PLAN.md — Domain Scoring Layer: Strategies, Calculator, Use Case + Unit Tests
- [ ] 02-02-PLAN.md — Infrastructure Wiring: Job, Endpoint, Routes, Provider + Feature Tests

### Phase 3: Events, Broadcasting & Polish
**Goal:** Wire domain events, log listener, Reverb broadcast, HTML/JS example, and finalize test suite.
**Mode:** mvp
**Requirements:** EVENT-01, EVENT-02, EVENT-03, EVENT-04, TEST-06
**Success criteria:**
1. Domain event dispatched on score completion
2. Log file written correctly
3. Reverb broadcast working
4. HTML/JS example in README
5. Full test suite passes

---

## Dependencies

- Phase 2 depends on Phase 1 (needs Contact entity + repository)
- Phase 3 depends on Phase 2 (needs score processing event)
