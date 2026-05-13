# Walking Skeleton — Mini CRM de Contatos

**Capability:** A user can create a contact via POST /api/contacts and view paginated contacts via GET /api/contacts, with data persisted in SQLite.

## Architectural Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Framework | Laravel 13 | Current stable (Mar 2026), PHP 8.3+, zero breaking changes from L12, long-term support |
| Architecture | DDD (Domain/Application/Infrastructure) | Spec requirement; strict layer separation; domain is framework-agnostic |
| Data layer | Eloquent ORM + SQLite (dev/test) | Eloquent is standard for Laravel; SQLite in-memory for fast tests; MySQL for production |
| Value Objects | readonly classes with self-validation | PHP 8.2+ readonly classes; compiler-enforced immutability for Email, Phone, Score |
| Status enum | PHP 8.1 backed string enum | Clean DB storage, compile-time safety, canTransitionTo() encodes state machine |
| Repository pattern | Domain interface + Eloquent implementation | Dependency inversion; domain defines contract, infra fulfills it |
| Service container | Laravel AppServiceProvider | Auto-resolves UseCase -> RepositoryInterface bindings |
| Input validation | Form Requests | Laravel built-in; automatic 422 responses; Rule::unique with whereNull(deleted_at) |
| Serialization | API Resources | Pagination-aware; standard JSON structure; ContactResource + ContactCollection |
| Phone normalization | Observer on saving event | Belt-and-suspenders with FormRequest prepareForValidation; strips all non-digits |
| Test DB | SQLite :memory: | Fast test execution; no external DB dependency; RefreshDatabase trait |
| Directory layout | src/Domain/, src/Application/, app/Infrastructure/ | PSR-4 autoloading; matches README convention; clear layer separation |

## Stack Touched

- [x] Project scaffold (Laravel 13, PSR-4 autoloading, directory structure)
- [x] Routing — POST /api/contacts and GET /api/contacts
- [x] Database — contacts migration + Eloquent model + create/persist + paginated read
- [x] UI — REST API (no visual UI; consumed by HTTP clients)
- [x] Deployment — documented in README.md: `composer install && cp .env.example .env && php artisan key:generate && php artisan migrate && php artisan serve`

## Out of Scope (deferred to later phases)

- Show, Update, Delete CRUD endpoints (Phase 1, Plan 02)
- Score processing (Phase 2)
- Domain events (Phase 3)
- Reverb broadcasting (Phase 3)
- HTML/JS listener example (Phase 3)
- Authentication (never required)

## Subsequent Slice Plan

- Phase 1, Plan 02: Complete CRUD (show, update, delete + feature tests)
- Phase 2: Score Processing (strategy pattern, queue job, process endpoint)
- Phase 3: Events, Broadcasting & Polish (domain events, log, Reverb, HTML example)
