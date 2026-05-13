# Walking Skeleton — Mini CRM de Contatos

**Phase:** 1
**Generated:** 2026-05-12

## Capability Proven End-to-End

A user can create a contact via `POST /api/contacts` and view paginated contacts via `GET /api/contacts`, with data persisted in SQLite. Contacts are stored with `status = pending` and `score = 0` by default. Phone numbers are normalized to digits-only on save.

## Architectural Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Framework | Laravel 13 | Current stable (Mar 2026), requires PHP 8.3+, zero breaking changes from L12, bug fixes until Q3 2027 |
| Architecture | DDD (Domain/Application/Infrastructure) | Spec requirement; strict layer separation; domain layer is 100% framework-agnostic |
| Entity pattern | Rich domain entity (not anemic) | Spec evaluation criteria penalize anemic entities; Contact has behavior methods with invariant enforcement |
| Value Objects | `readonly class` with self-validation | PHP 8.2+ readonly classes enforce immutability at compiler level; Email/Phone/Score VOs validate on construction |
| Status enum | PHP 8.1 backed string enum | Clean `pending`/`processing`/`active`/`failed` storage in DB; `canTransitionTo()` explicitly encodes state machine |
| Repository pattern | Domain interface (`src/Domain/Repositories/`) + Eloquent implementation (`app/Infrastructure/Repositories/`) | Dependency inversion: domain defines contract, infrastructure fulfills it; service container wires binding |
| Service container | Laravel `AppServiceProvider` | `register()` binds `ContactRepositoryInterface::class` → `EloquentContactRepository::class`; `boot()` registers `ContactObserver` |
| Pagination | Laravel `LengthAwarePaginator` | Accepted in Domain interface as pragmatic tradeoff per D-05; avoids custom pagination abstraction |
| Input validation | Form Requests (`StoreContactRequest`, `UpdateContactRequest`) | Laravel built-in; automatic 422 responses; `Rule::unique` with `whereNull('deleted_at')` for soft-delete-aware uniqueness |
| Serialization | API Resources (`ContactResource`, `ContactCollection`) | Pagination-aware; standard JSON structure; wraps Contact domain entity correctly |
| Phone normalization | Observer (`ContactObserver::saving`) + FormRequest `prepareForValidation()` | Belt-and-suspenders: FormRequest strips non-digits before validation; Observer strips again before persistence |
| Test DB | SQLite `:memory:` via `phpunit.xml` | Fast test execution; no external DB dependency; `RefreshDatabase` trait handles migration lifecycle |
| Source layout | `src/Domain/`, `src/Application/`, `app/Infrastructure/` | PSR-4 autoloading via `composer.json`; clear layer separation visible in directory structure |
| CRUD endpoints | Single controller with 5 methods, 5 use cases | Per D-10: controllers are thin, call use cases directly (no command bus); one use case per operation |
| Soft deletes | Eloquent `SoftDeletes` trait | Automatic query scoping; `withTrashed()`/`onlyTrashed()` available; standard Laravel pattern |
| Model factory | `ContactFactory` | Auto-generates test data; integrates with `RefreshDatabase` for test isolation |

## Stack Touched in Phase 1

- [x] Project scaffold (Laravel 13, PSR-4 autoloading, directory structure)
- [x] Routing — all 5 CRUD routes for `/api/contacts`
- [x] Database — contacts migration + Eloquent model + CRUD persistence
- [x] UI — REST API consumed via HTTP (no visual UI; Phase 3 will add HTML/JS example for Reverb)
- [x] Deployment — documented local run: `php artisan serve` after `composer install`, `.env` config, `migrate`

## Out of Scope (Deferred to Later Slices)

| Feature | Planned Phase |
|---------|--------------|
| Score processing (strategy pattern, queue job, processing endpoint) | Phase 2 |
| Domain events (`ContactScoreProcessed`) | Phase 3 |
| Log listener (`storage/logs/contact.log`) | Phase 3 |
| Reverb WebSocket broadcast (`contacts.{id}` channel) | Phase 3 |
| HTML/JS listener example in README | Phase 3 |
| Authentication / user management | Never required by spec |
| Frontend SPA | Never required by spec |
| Persistent WebSocket reconnection handling | Never required by spec |

## Subsequent Slice Plan

Each later phase adds one vertical slice on top of this skeleton without altering its architectural decisions:

- **Phase 2: Score Processing** — Strategy pattern for score calculation (email domain, name length, phone DDD rules), async queue job with `sleep(1-2)` simulation, `POST /api/contacts/{id}/process-score` trigger endpoint, status machine (`pending → processing → active|failed`), unit tests for domain/application
- **Phase 3: Events, Broadcasting & Polish** — `ContactScoreProcessed` domain event, log listener (`storage/logs/contact.log`), Reverb broadcast on `contacts.{id}` channel, HTML/JS example in README, full `php artisan test` suite passing
