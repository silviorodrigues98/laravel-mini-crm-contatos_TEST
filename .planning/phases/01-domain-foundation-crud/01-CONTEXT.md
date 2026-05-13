# Phase 1: Domain Foundation & CRUD — Context

**Gathered:** 2026-05-12
**Status:** Ready for planning

<domain>
## Phase Boundary

Implement the core domain layer (Contact entity, Email/Phone/Status value objects, repository interface), Laravel migration for contacts table, Eloquent repository implementation, full CRUD endpoints (POST/GET/PUT/DELETE) with Form Requests and API Resources, Observer for phone normalization, and feature tests for all CRUD operations.

This phase delivers the foundation that Phase 2 (Score Processing) and Phase 3 (Events, Broadcasting & Polish) build upon.

</domain>

<decisions>
## Implementation Decisions

### Entity Design
- **D-01:** Contact is a **rich domain entity** (not anemic). It exposes domain methods: `static create()`, `updateName()`, `updateEmail()`, `changePhone()`, `markAsProcessing()`, `markAsActive()`, `markAsFailed()`. Constructor receives Value Objects (Email, Phone, Status) — never raw strings.
- **D-02:** `Contact::create()` is a static named constructor that builds the entity with Status = `pending` and Score = 0. Returns a clean Contact instance.
- **D-03:** Status transitions are managed by dedicated methods on Contact (not external services). Each transition validates that the current status allows the requested new status.

### Repository Granularity
- **D-04:** Repository interface (`ContactRepositoryInterface`) exposes: `save(Contact): void`, `findById(int): ?Contact`, `findAll(PaginationCriteria): LengthAwarePaginator`, `delete(int): void`.
- **D-05:** Pagination uses Laravel's `LengthAwarePaginator` so the API Resource can return standard pagination metadata.
- **D-06:** Repository lives in `src/Domain/Repositories/` as an interface. Eloquent implementation in `app/Infrastructure/Repositories/`.

### Application Layer Structure
- **D-07:** One UseCase per CRUD operation: `CreateContactUseCase`, `UpdateContactUseCase`, `DeleteContactUseCase`, `GetContactUseCase`, `ListContactsUseCase`.
- **D-08:** Each UseCase receives the `ContactRepositoryInterface` via constructor injection. Laravel service container auto-resolves bindings.
- **D-09:** UseCases belong in `src/Application/UseCases/`.
- **D-10:** Controllers call UseCases directly (no command bus — keeping it simple for MVP).

### Source Directory Layout
- **D-11:** `src/Domain/`, `src/Application/`, `app/Infrastructure/` — matching the README convention.
- **D-12:** PSR-4 autoloading entry in `composer.json`: `"App\\": "app/"` (standard) + `"Domain\\": "src/Domain/"` + `"Application\\": "src/Application/"`.

### Phone Normalization (Observer)
- **D-13:** Observer on `saving` event strips all non-digit characters from phone.
- **D-14:** Stored format: DDD + number digits (e.g., `11999999999`). No country code — not required by spec.
- **D-15:** Observer lives at `app/Infrastructure/Observers/ContactObserver.php`.
- **D-16:** Phone validation in FormRequest ensures at least 10 digits (DDD + number).

### Feature Test Depth
- **D-17:** Feature tests cover: create (valid + validation errors), list (paginated format), show (existing + 404), update (valid + validation errors), delete (soft-delete + 404 after delete).
- **D-18:** Tests use `RefreshDatabase` trait. Test class: `tests/Feature/ContactApiTest.php`.

### the agent's Discretion
- Exception handling strategy in controllers (return 422 vs 400 for validation) — default to FormRequest behavior.
- Test data factory approach — default to `ContactFactory` or inline setup.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Spec (Immutable Source of Truth)
- `docs/original/README.md` — Original challenge spec. MUST be followed to the letter. Defines all entities, endpoints, rules, and evaluation criteria.

### Planning Docs
- `.planning/ROADMAP.md` — Phase definitions, success criteria (7 for Phase 1), dependency map.
- `.planning/REQUIREMENTS.md` — All 35 requirements with traceability. Phase 1 covers CONT-01–05, ARCH-01–10, TEST-04.
- `.planning/PROJECT.md` — Project-level decisions (DDD, Strategy, Redis, Reverb), constraints, core value.

### Project Documentation
- `README.md` — Project README in Brazilian Portuguese. Documents architecture, endpoints, setup, and test commands.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **None** — Greenfield project. No Laravel scaffold exists yet. Everything in this phase is first-of-its-kind.

### Established Patterns
- **None** — No existing codebase patterns to follow.

### Integration Points
- **None** — Phase 1 is the foundation. Phase 2 will connect to the repository and entity defined here.

</code_context>

<specifics>
## Specific Ideas

- README must stay in Brazilian Portuguese as currently written.
- `docs/original/README.md` is the immutable spec — every endpoint, behavior, and rule defined there must be implemented.
- Evaluation criteria penalize anemic entities — entities must be rich with domain behavior.
- Phone normalization in Observer (ARCH-08), structured according to phone normalization rules defined in D-13/D-14.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 1-Domain Foundation & CRUD*
*Context gathered: 2026-05-12*
