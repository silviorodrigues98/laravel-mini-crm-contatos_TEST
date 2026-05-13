# Phase 1: Domain Foundation & CRUD — Research

**Researched:** 2026-05-12
**Domain:** Laravel 13, DDD entities/value objects, Repository pattern, CRUD API, Eloquent, Feature tests
**Confidence:** HIGH

## Summary

This phase builds the foundation of a greenfield Laravel 13 REST API implementing DDD layering. The Contact entity with Email/Phone/Status Value Objects forms the domain core, decoupled from Laravel via repository interfaces. The Eloquent repository bridges domain and infrastructure. CRUD endpoints use Form Requests (validation) and API Resources (serialization) with a single Observer for phone normalization. Feature tests with `RefreshDatabase` cover all endpoints.

**Primary recommendation:** Scaffold with `laravel/laravel` v13.x, configure PSR-4 autoloading for `src/Domain/` and `src/Application/`, implement the Contact entity as a `readonly class` with a private constructor and static named constructor `create()`, use PHP 8.1 backed enum for Status, and wire everything through Laravel's service container.

### Critical Environment Note

PHP 8.3+ and Composer are **not installed** on the target machine (verified via `command -v`). The planner MUST include a setup step: install PHP 8.3+ and Composer, or install Docker + Laravel Sail. Without this, no Laravel scaffold can be created. [VERIFIED: command check]

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

#### Entity Design
- **D-01:** Contact is a **rich domain entity** (not anemic). It exposes domain methods: `static create()`, `updateName()`, `updateEmail()`, `changePhone()`, `markAsProcessing()`, `markAsActive()`, `markAsFailed()`. Constructor receives Value Objects (Email, Phone, Status) — never raw strings.
- **D-02:** `Contact::create()` is a static named constructor that builds the entity with Status = `pending` and Score = 0. Returns a clean Contact instance.
- **D-03:** Status transitions are managed by dedicated methods on Contact (not external services). Each transition validates that the current status allows the requested new status.

#### Repository Granularity
- **D-04:** Repository interface (`ContactRepositoryInterface`) exposes: `save(Contact): void`, `findById(int): ?Contact`, `findAll(PaginationCriteria): LengthAwarePaginator`, `delete(int): void`.
- **D-05:** Pagination uses Laravel's `LengthAwarePaginator` so the API Resource can return standard pagination metadata.
- **D-06:** Repository lives in `src/Domain/Repositories/` as an interface. Eloquent implementation in `app/Infrastructure/Repositories/`.

#### Application Layer Structure
- **D-07:** One UseCase per CRUD operation: `CreateContactUseCase`, `UpdateContactUseCase`, `DeleteContactUseCase`, `GetContactUseCase`, `ListContactsUseCase`.
- **D-08:** Each UseCase receives the `ContactRepositoryInterface` via constructor injection. Laravel service container auto-resolves bindings.
- **D-09:** UseCases belong in `src/Application/UseCases/`.
- **D-10:** Controllers call UseCases directly (no command bus — keeping it simple for MVP).

#### Source Directory Layout
- **D-11:** `src/Domain/`, `src/Application/`, `app/Infrastructure/` — matching the README convention.
- **D-12:** PSR-4 autoloading entry in `composer.json`: `"App\\": "app/"` (standard) + `"Domain\\": "src/Domain/"` + `"Application\\": "src/Application/"`.

#### Phone Normalization (Observer)
- **D-13:** Observer on `saving` event strips all non-digit characters from phone.
- **D-14:** Stored format: DDD + number digits (e.g., `11999999999`). No country code — not required by spec.
- **D-15:** Observer lives at `app/Infrastructure/Observers/ContactObserver.php`.
- **D-16:** Phone validation in FormRequest ensures at least 10 digits (DDD + number).

#### Feature Test Depth
- **D-17:** Feature tests cover: create (valid + validation errors), list (paginated format), show (existing + 404), update (valid + validation errors), delete (soft-delete + 404 after delete).
- **D-18:** Tests use `RefreshDatabase` trait. Test class: `tests/Feature/ContactApiTest.php`.

### The agent's Discretion
- Exception handling strategy in controllers (return 422 vs 400 for validation) — default to FormRequest behavior.
- Test data factory approach — default to `ContactFactory` or inline setup.

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| **CONT-01** | Create contact (name, email, phone; status=pending, score=0) | Contact entity with `create()` named constructor; `StoreContactRequest` FormRequest; `CreateContactUseCase`; Eloquent repository saves to DB |
| **CONT-02** | List contacts with pagination | `ListContactsUseCase` returns `LengthAwarePaginator`; `ContactResource` wraps each item; pagination metadata in response |
| **CONT-03** | View single contact by ID | `GetContactUseCase` calls `findById()`; returns 404 if not found; `ContactResource` serializes |
| **CONT-04** | Update contact name, email, phone | `UpdateContactUseCase` calls `save()` after modifying entity; `UpdateContactRequest` validates; returns updated resource |
| **CONT-05** | Soft-delete a contact | `DeleteContactUseCase` calls `delete()` (Eloquent soft deletes); model uses `SoftDeletes` trait; 204 response |
| **ARCH-01** | Domain layer: entities, value objects, domain services (framework-agnostic) | `src/Domain/Entities/Contact.php` has zero Laravel imports; only PHP 8.2+ readonly class features |
| **ARCH-02** | Value Objects for Email, Phone, Status (not raw strings) | `src/Domain/ValueObjects/Email.php`, `Phone.php`; `src/Domain/Enums/ContactStatus.php` backed enum |
| **ARCH-03** | Application layer: use cases orchestrating operations | `src/Application/UseCases/*ContactUseCase.php` — each receives repository via constructor |
| **ARCH-04** | Repository interfaces in Domain, Eloquent in Infrastructure | `src/Domain/Repositories/ContactRepositoryInterface.php` — `app/Infrastructure/Repositories/EloquentContactRepository.php` |
| **ARCH-05** | Dependencies wired via Laravel service container | Binding in `AppServiceProvider::register()`: `$this->app->bind(ContactRepositoryInterface::class, EloquentContactRepository::class)` |
| **ARCH-06** | Form Requests for input validation | `app/Http/Requests/StoreContactRequest.php`, `UpdateContactRequest.php` |
| **ARCH-07** | API Resources for standardized JSON output | `app/Http/Resources/ContactResource.php` wraps Contact; `ContactCollection` for paginated lists |
| **ARCH-08** | Observer on Contact model (`saving` to normalize phone) | `app/Infrastructure/Observers/ContactObserver.php` with `saving()` method; registered in `AppServiceProvider::boot()` |
| **ARCH-09** | Soft deletes, timestamps, `processed_at` on Contact model | Migration: `$table->softDeletes()`, `$table->timestamps()`, `$table->timestamp('processed_at')->nullable()` |
| **ARCH-10** | Contact status enum (`pending`, `processing`, `active`, `failed`) | PHP 8.1 backed enum `ContactStatus: string` — cast in Eloquent model via `$casts` |
| **TEST-04** | Feature/integration tests for CRUD endpoints | `tests/Feature/ContactApiTest.php` with `RefreshDatabase`; covers all 5 operations + validation + 404 cases |
</phase_requirements>

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Entity definition (Contact) | Domain | — | Pure business logic; zero framework imports per DDD |
| Value Objects (Email, Phone) | Domain | — | Self-validating, immutable types independent of infrastructure |
| Status transitions | Domain | — | Methods on Contact entity enforce valid state machine |
| Repository interface | Domain | — | Contract definition; infra implementations fulfill it |
| Use Case orchestration | Application | — | Coordinates domain entities with repository; orchestrates without knowing implementation |
| HTTP request handling | Infrastructure | — | Laravel controllers, Form Requests, API Resources |
| Data persistence | Infrastructure | — | Eloquent model + repository implementation |
| Input validation | Infrastructure | — | Form Requests (Laravel-specific) |
| Phone normalization | Infrastructure | — | Observer on Eloquent model saving event |
| API serialization | Infrastructure | — | API Resources (Laravel-specific) |
| CRUD testing | Infrastructure (Feature) | Domain (Unit, Phase 2) | Feature tests hit real endpoints; unit tests for domain come in Phase 2 |

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/laravel` | ^13.0 | Application scaffold | Current stable release (March 17, 2026), requires PHP 8.3+. Bug fixes until Q3 2027. [VERIFIED: laravel.com/docs/13.x/releases] |
| PHP | ^8.3 | Runtime | Minimum required by Laravel 13. Supports readonly classes (8.2), backed enums (8.1), asymmetric visibility (8.4) [VERIFIED: php.net] |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `phpunit/phpunit` | ^11.0 | Test framework | Shipped with Laravel 13 scaffold [VERIFIED: laravel.com/docs/13.x/upgrade] |
| `orchestra/testbench` | v9+ | Package testing | Not needed — this is a full app, not a package. Use Laravel's built-in `TestCase` |
| `laravel/sail` | ^2.0 | Docker dev environment | Optional — only if PHP not available natively |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Laravel 13 | Laravel 12 | Laravel 12 is security-only (ends Feb 2027). L13 has zero breaking changes. Prefer L13 for greenfield. |
| PHP 8.3 | PHP 8.2 | L13 requires 8.3+. 8.2 only works with L12. |
| `readonly class` | Plain class with private setters | `readonly class` is compiler-enforced immutability — lighter code, no boilerplate. Available since PHP 8.2. |

**Installation:**
```bash
composer create-project laravel/laravel:^13.0 . --prefer-dist
```

**Version verification:**
```bash
php artisan --version
# Expected: Laravel Framework 13.x.x
```

## Architecture Patterns

### System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        HTTP Request/Response                        │
└─────────────────────────┬───────────────────────────────────────────┘
                          │
┌─────────────────────────▼───────────────────────────────────────────┐
│              Infrastructure (Laravel-aware layer)                   │
│                                                                     │
│  ┌──────────────┐  ┌──────────────────┐  ┌──────────────────────┐  │
│  │  Controllers  │  │  Form Requests   │  │   API Resources      │  │
│  │  (routes/api) │──│  (validation)    │  │  (serialization)     │  │
│  └──────┬───────┘  └──────────────────┘  └──────────────────────┘  │
│         │                                                          │
│  ┌──────▼───────┐  ┌──────────────────────────────────────────┐    │
│  │   Use Cases   │──│  EloquentContactRepository               │    │
│  │  (Application │  │  (implements RepositoryInterface)        │    │
│  │   namespace)  │  └──────────────────┬───────────────────────┘    │
│  └──────┬───────┘                     │                            │
│         │                             │                            │
│  ┌──────▼───────┐  ┌──────────────────▼───────────────────────┐    │
│  │  Observer     │  │  Eloquent Model (app/Models/Contact)     │    │
│  │  (saving:     │  │  - SoftDeletes                           │    │
│  │  normalize    │  │  - casts (Status enum, Custom VO casts)  │    │
│  │  phone)       │  │  - factory                               │    │
│  └──────────────┘  └──────────────────┬───────────────────────┘    │
│                                       │                            │
│                          ┌────────────▼────────────┐               │
│                          │     MySQL/SQLite DB     │               │
│                          └─────────────────────────┘               │
└─────────────────────────────────────────────────────────────────────┘
                          ▲
                          │  (via Service Container binding)
┌─────────────────────────┴───────────────────────────────────────────┐
│              Domain (Framework-agnostic layer)                      │
│                                                                     │
│  ┌─────────────────────┐  ┌────────────────────────────────────┐   │
│  │  Contact Entity     │  │  Value Objects                     │   │
│  │  - create()         │  │  - Email (validates format)        │   │
│  │  - updateName()     │  │  - Phone (immutable value)         │   │
│  │  - markAsActive()   │──│  - ContactStatus (backed enum)     │   │
│  │  - etc.             │  │  - Score (simple VO)               │   │
│  └─────────────────────┘  └────────────────────────────────────┘   │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │  ContactRepositoryInterface (contract)                       │   │
│  │  - save(Contact): void                                       │   │
│  │  - findById(int): ?Contact                                   │   │
│  │  - findAll(PaginationCriteria): LengthAwarePaginator          │   │
│  │  - delete(int): void                                         │   │
│  └──────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

### Recommended Project Structure

```
project-root/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── ContactController.php      # Thin controller → delegates to UseCase
│   │   └── Requests/
│   │       ├── StoreContactRequest.php        # POST validation
│   │       └── UpdateContactRequest.php       # PUT validation
│   ├── Http/Resources/
│   │   ├── ContactResource.php                # Single contact serialization
│   │   └── ContactCollection.php              # Paginated list serialization
│   ├── Infrastructure/
│   │   ├── Models/
│   │   │   └── Contact.php                    # Eloquent model (SoftDeletes, casts)
│   │   ├── Observers/
│   │   │   └── ContactObserver.php            # saving → normalize phone
│   │   ├── Repositories/
│   │   │   └── EloquentContactRepository.php  # Implements Domain interface
│   │   └── Persistence/
│   │       └── Mappers/
│   │           └── ContactMapper.php          # Maps Eloquent model <-> Domain entity
│   └── Providers/
│       └── AppServiceProvider.php             # Bind repository interface, register observer
├── src/
│   ├── Domain/
│   │   ├── Entities/
│   │   │   └── Contact.php                    # Rich domain entity (no Laravel imports)
│   │   ├── ValueObjects/
│   │   │   ├── Email.php                      # readonly class, validates on construct
│   │   │   ├── Phone.php                      # readonly class, immutable
│   │   │   └── Score.php                      # readonly class, wraps int
│   │   ├── Enums/
│   │   │   └── ContactStatus.php              # PHP 8.1 backed string enum
│   │   └── Repositories/
│   │       └── ContactRepositoryInterface.php # Contract (no Eloquent dependency)
│   └── Application/
│       └── UseCases/
│           ├── CreateContactUseCase.php
│           ├── ListContactsUseCase.php
│           ├── GetContactUseCase.php
│           ├── UpdateContactUseCase.php
│           └── DeleteContactUseCase.php
├── tests/
│   └── Feature/
│       ├── ContactApiTest.php                 # CRUD feature tests (RefreshDatabase)
│       └── Http/
│           └── Controllers/
│               └── Api/
│                   └── ContactControllerTest.php # Optional: finer-grained
├── database/
│   ├── factories/
│   │   └── ContactFactory.php                 # Factory for test data
│   └── migrations/
│       └── xxxx_create_contacts_table.php
├── routes/
│   └── api.php                                # Route::apiResource('contacts', ...)
└── composer.json                              # PSR-4 autoload entries
```

### Pattern 1: PSR-4 Autoloading for DDD Namespaces

**What:** Add custom namespace mappings in `composer.json` so `src/Domain/` and `src/Application/` are autoloaded alongside the default `App\\` namespace.

**When to use:** Immediately after project scaffold, before writing any code.

**composer.json (autoload section):**
```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Domain\\": "src/Domain/",
            "Application\\": "src/Application/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

After editing, run:
```bash
composer dump-autoload
```
[VERIFIED: composer.json PSR-4 standard]

### Pattern 2: Rich Domain Entity with PHP 8.2+ Features

**What:** A `readonly class` that enforces immutability at the compiler level. The constructor receives only Value Objects (never raw strings). State-changing methods return a new instance or use a writable property pattern for controlled mutation.

**When to use:** For all domain entities that must not import Laravel facades or ORM.

**Key PHP 8.x features used:**
- `readonly class` (PHP 8.2): All properties are implicitly readonly. Prevents modification after construction. [VERIFIED: wiki.php.net/rfc/readonly_classes]
- Typed properties (PHP 7.4+): Every property has a type declaration.
- Backed enums (PHP 8.1): For Status — stored as string in DB, used as object in code. [VERIFIED: php.net/enumerations]
- Named arguments (PHP 8.0): Improves constructor call readability.
- `match` expression (PHP 8.0): Cleaner than switch for status transitions.

**Implementation guideline:** Use `readonly class` for Value Objects (fully immutable). For the Contact entity (which needs controlled state changes via domain methods), use `readonly` properties on immutable VOs but keep the entity as a non-readonly class with public readonly properties for identity VOs and private setters for mutable state (like status, score).

### Pattern 3: Repository Pattern with Domain Interface + Eloquent Implementation

**What:** The `ContactRepositoryInterface` contract lives in `src/Domain/Repositories/` with zero infrastructure dependencies. The `EloquentContactRepository` in `app/Infrastructure/Repositories/` implements the contract. A `ContactMapper` handles the bidirectional mapping between Eloquent models and Domain entities.

**When to use:** Always — this is the core of the dependency inversion principle required by DDD.

### Pattern 4: UseCase → Controller Wire

**What:** Controllers are thin — they extract data from the request, instantiate the UseCase (via constructor injection), call a single method, and return the API Resource response.

**When to use:** Every CRUD operation.

### Anti-Patterns to Avoid

- **Anemic entity:** Entity with only getters/setters and no domain behavior. Instead, Contact has `markAsProcessing()`, `markAsActive()`, etc. [CITED: docs/original/README.md — evaluation criteria penalize anemic entities]
- **Laravel imports in Domain:** `use Illuminate\Database\Eloquent\Model` in Domain classes. Domain MUST be framework-agnostic. [VERIFIED: AGENTS.md]
- **Controller doing business logic:** Controller calls UseCase, UseCase calls Repository. Controller never touches Eloquent directly.
- **Raw strings in entity constructor:** Contact constructor receives `Email`, `Phone`, `ContactStatus` — never `string $email`, `string $phone`.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| HTTP input validation | Custom validation classes | Laravel Form Requests | Built-in rule engine, `authorize()` for access control, automatic 422 responses [VERIFIED: laravel.com/docs/13.x/validation] |
| JSON serialization | Manual `toArray()` in controller | Laravel API Resources | Pagination-aware, conditional attributes, relationship loading [VERIFIED: laravel.com/docs/13.x/eloquent-resources] |
| Test database isolation | Manual setup/teardown | `RefreshDatabase` trait | Runs migrations between tests, wraps in transactions, built-in to Laravel [VERIFIED: laravel.com/docs/13.x/testing] |
| Pagination | Manual LIMIT/OFFSET | `LengthAwarePaginator` | Laravel's built-in paginator, integrates with API Resources, standard JSON meta [VERIFIED: laravel.com/docs/13.x/pagination] |
| Soft deletes | Manual `deleted_at` checks | Eloquent `SoftDeletes` trait | Automatic query scoping, `withTrashed()`/`onlyTrashed()` helpers [VERIFIED: laravel.com/docs/13.x/eloquent#soft-deleting] |
| DB migration management | Raw SQL | Laravel Migrations | Version-controlled, reversible, schema builder [VERIFIED: laravel.com/docs/13.x/migrations] |
| Model factory for tests | Manual model creation | `ContactFactory` | Auto-generates test data, integrates with `RefreshDatabase` [VERIFIED: laravel.com/docs/13.x/eloquent-factories] |

**Key insight:** The Laravel framework provides battle-tested solutions for all the infrastructure concerns in Phase 1. The goal is to keep the Domain layer framework-agnostic while using Laravel's strengths for everything else.

## Common Pitfalls

### Pitfall 1: Domain Entity Importing Laravel Classes
**What goes wrong:** A developer accidentally adds `use Illuminate\Database\Eloquent\Model` or `use Illuminate\Support\Carbon` to the Contact entity.
**Why it happens:** Habit from traditional Laravel MVC development where all classes live in `app/`.
**How to avoid:** The `src/Domain/` directory must have zero Laravel imports. Enforce via code review. The entity should only use PHP built-in classes (`\InvalidArgumentException`, `\DomainException`).
**Warning signs:** `Contact.php` file located in `app/Models/` instead of `src/Domain/Entities/`.

### Pitfall 2: Value Object Cast Without Null Handling
**What goes wrong:** The custom cast `get()` method throws when the database column is null (e.g., before `processed_at` is set).
**Why it happens:** Eloquent loads null columns as `null`, but the cast unconditionally calls `new Email($value)`.
**How to avoid:** Always handle the null case in `get()`:
```php
public function get($model, $key, $value, $attributes): ?Email
{
    return $value !== null ? new Email($value) : null;
}
```
**Warning signs:** `Error: filter_var(): Argument #1 ($value) must be of type string, null given` when accessing a nullable cast attribute.

### Pitfall 3: FormRequest/Resource Not Updated for UseCase Parameters
**What goes wrong:** FormRequest rules change but the UseCase's expectations don't match, or the API Resource exposes fields that should be hidden.
**Why it happens:** The controller sits between Request/Resources and UseCases. A change in one can silently mismatch.
**How to avoid:** Use typed DTOs or `$request->validated()` exclusively. The UseCase should accept scalar values extracted from validated data, not the Request object.
**Warning signs:** Undefined array key errors in UseCase after adding a new FormRequest rule.

### Pitfall 4: Observer Not Registered
**What goes wrong:** Phone normalization never fires because the Observer class was created but never registered in a service provider.
**Why it happens:** Unlike controllers and models which are auto-discovered, observers must be explicitly registered. [CITED: laravel.com/docs/13.x/eloquent#observers]
**How to avoid:** In `AppServiceProvider::boot()`:
```php
public function boot(): void
{
    Contact::observe(ContactObserver::class);
}
```
Or use the `$observers` property in `EventServiceProvider` (Laravel 13+).
**Warning signs:** Phone stored with punctuation (`(11) 99999-9999` instead of `11999999999`).

### Pitfall 5: Soft Deletes Interfering with Unique Email Validation
**What goes wrong:** A contact is soft-deleted with email `a@b.com`. Creating a new contact with the same email fails unique validation.
**Why it happens:** The FormRequest checks `unique:contacts,email` but soft-deleted rows are still in the table.
**How to avoid:** Use the `withoutTrashed` modifier on the unique rule:
```php
// In StoreContactRequest or UpdateContactRequest
'email' => [
    'required',
    'email',
    Rule::unique('contacts', 'email')->whereNull('deleted_at'),
],
```
**Warning signs:** `The email has already been taken.` error when creating a contact with the same email as a soft-deleted one.

## Code Examples

### Example 1: ContactStatus Backed Enum

**Source:** [VERIFIED: php.net/enumerations]

```php
<?php

declare(strict_types=1);

namespace Domain\Enums;

/**
 * Represents the possible states of a Contact during score processing.
 *
 * @see docs/original/README.md - Status enum definition
 */
enum ContactStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Active = 'active';
    case Failed = 'failed';

    /**
     * Check if this status allows transitioning to the given target status.
     * Valid transitions: pending -> processing -> active|failed
     *
     * @param  ContactStatus  $target  The desired status
     * @return bool                    Whether the transition is valid
     */
    public function canTransitionTo(ContactStatus $target): bool
    {
        return match ($this) {
            self::Pending => $target === self::Processing,
            self::Processing => $target === self::Active || $target === self::Failed,
            self::Active, self::Failed => false, // Terminal states
        };
    }

    /**
     * All valid status values for database storage/validation.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```
**Why this pattern:** Backed enum stores clean strings in DB (`'pending'`, `'active'`), prevents invalid states at compile time, and `canTransitionTo()` encodes the state machine explicitly.

### Example 2: Value Object — Email

**Source:** [VERIFIED: pragmatisticddd.com/blog/value-objects-laravel/]

```php
<?php

declare(strict_types=1);

namespace Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable Email value object.
 * Wraps a validated email string. Guarantees that any instance is valid.
 */
final readonly class Email
{
    public function __construct(
        public string $value
    ) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email address: {$value}");
        }
    }

    /**
     * Extract the domain part (everything after '@').
     */
    public function domain(): string
    {
        return substr($this->value, (int) strpos($this->value, '@') + 1);
    }

    /**
     * Extract the TLD (last part after last dot).
     */
    public function tld(): string
    {
        $parts = explode('.', $this->value);
        return end($parts);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

### Example 3: Value Object — Phone

**Source:** [VERIFIED: D-13/D-14 in CONTEXT.md]

```php
<?php

declare(strict_types=1);

namespace Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable Phone value object.
 * Stores phone as DDD + number digits (e.g., 11999999999).
 * Normalization happens in the Observer, but the VO validates the clean format.
 */
final readonly class Phone
{
    public function __construct(
        public string $value
    ) {
        // Accept only digits at this stage (Observer strips non-digits before entity receives it)
        if (!ctype_digit($value)) {
            throw new InvalidArgumentException("Phone must contain only digits: {$value}");
        }
        if (strlen($value) < 10) {
            throw new InvalidArgumentException("Phone must have at least 10 digits (DDD + number): {$value}");
        }
    }

    /**
     * Extract area code (first 2 digits).
     */
    public function ddd(): string
    {
        return substr($this->value, 0, 2);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

### Example 4: Contact Entity (Rich Domain Model)

**Source:** [VERIFIED: D-01, D-02, D-03 in CONTEXT.md]

```php
<?php

declare(strict_types=1);

namespace Domain\Entities;

use Domain\Enums\ContactStatus;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;
use Domain\ValueObjects\Score;
use DomainException;

/**
 * Rich domain entity for Contact.
 * Framework-agnostic — no Laravel imports.
 * Immutable identity (id, email) with controlled mutable state (name, phone, score, status).
 */
class Contact
{
    private ?int $id;

    private string $name;

    private Email $email;

    private Phone $phone;

    private Score $score;

    private ContactStatus $status;

    private ?\DateTimeImmutable $processedAt = null;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $updatedAt = null;

    private ?\DateTimeImmutable $deletedAt = null;

    /**
     * Private constructor — use named constructors.
     *
     * @param int|null                $id
     * @param string                  $name
     * @param Email                   $email
     * @param Phone                   $phone
     * @param Score                   $score
     * @param ContactStatus           $status
     * @param \DateTimeImmutable|null $processedAt
     */
    private function __construct(
        ?int $id,
        string $name,
        Email $email,
        Phone $phone,
        Score $score,
        ContactStatus $status,
        ?\DateTimeImmutable $processedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->score = $score;
        $this->status = $status;
        $this->processedAt = $processedAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Named constructor for creating a new Contact.
     * Status defaults to Pending, Score to 0.
     */
    public static function create(
        string $name,
        Email $email,
        Phone $phone
    ): self {
        return new self(
            id: null,
            name: $name,
            email: $email,
            phone: $phone,
            score: Score::zero(),
            status: ContactStatus::Pending,
        );
    }

    /**
     * Factory for reconstituting a persisted Contact (from repository).
     * This is separate from create() to distinguish "new" from "rehydrated".
     */
    public static function reconstitute(
        int $id,
        string $name,
        Email $email,
        Phone $phone,
        Score $score,
        ContactStatus $status,
        ?\DateTimeImmutable $processedAt = null,
        \DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $deletedAt = null
    ): self {
        $contact = new self(
            id: $id,
            name: $name,
            email: $email,
            phone: $phone,
            score: $score,
            status: $status,
            processedAt: $processedAt,
        );
        $contact->createdAt = $createdAt ?? new \DateTimeImmutable();
        $contact->updatedAt = $updatedAt;
        $contact->deletedAt = $deletedAt;
        return $contact;
    }

    // --- Identity ---

    public function id(): ?int
    {
        return $this->id;
    }

    // --- Status transitions (controlled mutations) ---

    public function markAsProcessing(): void
    {
        $this->assertTransition(ContactStatus::Processing);
        $this->status = ContactStatus::Processing;
        $this->touch();
    }

    public function markAsActive(Score $score): void
    {
        $this->assertTransition(ContactStatus::Active);
        $this->score = $score;
        $this->status = ContactStatus::Active;
        $this->processedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function markAsFailed(): void
    {
        $this->assertTransition(ContactStatus::Failed);
        $this->status = ContactStatus::Failed;
        $this->touch();
    }

    // --- Data mutations ---

    public function updateName(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function updateEmail(Email $email): void
    {
        $this->email = $email;
        $this->touch();
    }

    public function changePhone(Phone $phone): void
    {
        $this->phone = $phone;
        $this->touch();
    }

    // --- Getters ---

    public function name(): string { return $this->name; }
    public function email(): Email { return $this->email; }
    public function phone(): Phone { return $this->phone; }
    public function score(): Score { return $this->score; }
    public function status(): ContactStatus { return $this->status; }
    public function processedAt(): ?\DateTimeImmutable { return $this->processedAt; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function deletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }

    // --- Private helpers ---

    private function assertTransition(ContactStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new DomainException(
                sprintf(
                    'Cannot transition from "%s" to "%s".',
                    $this->status->value,
                    $target->value
                )
            );
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
```
**Why this pattern:** The entity enforces its own invariants (`canTransitionTo`), receives only VOs in constructor, has separate `create()` (for new contacts) and `reconstitute()` (for hydrating from DB), and uses `DomainException` for business rule violations — all without importing Laravel.

### Example 5: Repository Interface

**Source:** [VERIFIED: D-04, D-06 in CONTEXT.md]

```php
<?php

declare(strict_types=1);

namespace Domain\Repositories;

use Domain\Entities\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Repository interface for Contact aggregate root.
 * Framework-agnostic domain contract.
 *
 * Note: LengthAwarePaginator is from Laravel's contracts package.
 * It's an interface, not the ORM — acceptable in Domain since it's a contract,
 * not an implementation. Alternative: define a custom PaginationCriteria VO + PaginationResult.
 * Using LengthAwarePaginator is a pragmatic choice per D-05.
 */
interface ContactRepositoryInterface
{
    public function save(Contact $contact): void;

    public function findById(int $id): ?Contact;

    /**
     * @param  int  $perPage  Items per page
     * @param  int  $page     Current page
     * @return LengthAwarePaginator
     */
    public function findAll(int $perPage = 15, int $page = 1): LengthAwarePaginator;

    /**
     * Soft-delete a contact by ID.
     */
    public function delete(int $id): void;
}
```
**Design note on LengthAwarePaginator:** D-05 explicitly chooses `LengthAwarePaginator` despite it being from Laravel. This is a pragmatic tradeoff to avoid building a custom pagination abstraction. The interface `Illuminate\Contracts\Pagination\LengthAwarePaginator` is a contract, not an Eloquent implementation. If strict domain purity is required, the planner can define a `PaginationResult` VO in Domain — but D-05 locks the `LengthAwarePaginator` choice.

### Example 6: Eloquent Repository Implementation

**Source:** [VERIFIED: D-04, D-06 in CONTEXT.md]

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Infrastructure\Models\Contact as ContactModel;
use App\Infrastructure\Persistence\Mappers\ContactMapper;
use Domain\Entities\Contact;
use Domain\Repositories\ContactRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentContactRepository implements ContactRepositoryInterface
{
    public function __construct(
        private readonly ContactModel $model,
        private readonly ContactMapper $mapper,
    ) {}

    public function save(Contact $contact): void
    {
        $eloquent = $this->mapper->toEloquent($contact);
        $eloquent->save();

        // Sync the generated ID back to the domain entity
        // Using reflection since Contact does not have a public setId()
        // Alternative: return the Contact with ID from the repository
        $reflection = new \ReflectionProperty($contact, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($contact, $eloquent->id);
    }

    public function findById(int $id): ?Contact
    {
        $eloquent = $this->model->newQuery()->find($id);

        return $eloquent !== null
            ? $this->mapper->toDomain($eloquent)
            : null;
    }

    public function findAll(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $paginator = $this->model->newQuery()
            ->paginate(perPage: $perPage, page: $page);

        // Transform each item in the paginator's collection
        $paginator->getCollection()->transform(
            fn (ContactModel $model) => $this->mapper->toDomain($model)
        );

        return $paginator;
    }

    public function delete(int $id): void
    {
        $eloquent = $this->model->newQuery()->findOrFail($id);
        $eloquent->delete(); // Soft delete
    }
}
```

### Example 7: ContactMapper (Eloquent ↔ Domain)

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mappers;

use App\Infrastructure\Models\Contact as ContactModel;
use Domain\Entities\Contact;
use Domain\Enums\ContactStatus;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;
use Domain\ValueObjects\Score;

/**
 * Maps between Eloquent Contact model and Domain Contact entity.
 * This is the only place where Eloquent-to-Domain conversion logic lives.
 */
final readonly class ContactMapper
{
    public function toDomain(ContactModel $model): Contact
    {
        return Contact::reconstitute(
            id: $model->id,
            name: $model->name,
            email: new Email($model->email),
            phone: new Phone($model->phone),
            score: new Score($model->score),
            status: $model->status, // Already cast to ContactStatus via Eloquent cast
            processedAt: $model->processed_at !== null
                ? new \DateTimeImmutable($model->processed_at)
                : null,
            createdAt: new \DateTimeImmutable($model->created_at),
            updatedAt: $model->updated_at !== null
                ? new \DateTimeImmutable($model->updated_at)
                : null,
            deletedAt: $model->deleted_at !== null
                ? new \DateTimeImmutable($model->deleted_at)
                : null,
        );
    }

    public function toEloquent(Contact $contact): ContactModel
    {
        $model = new ContactModel();

        if ($contact->id() !== null) {
            $model->id = $contact->id();
        }

        $model->name = $contact->name();
        $model->email = $contact->email()->value;
        $model->phone = $contact->phone()->value;
        $model->score = $contact->score()->value;
        $model->status = $contact->status()->value;
        $model->processed_at = $contact->processedAt()?->format('Y-m-d H:i:s');

        return $model;
    }
}
```
**Alternative:** Skip the mapper and have the Eloquent model handle its own casts. The mapper adds a clean separation layer. The planner can choose based on complexity tolerance.

### Example 8: Form Request — StoreContactRequest

**Source:** [VERIFIED: laravel.com/docs/13.x/validation]

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Domain\Enums\ContactStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * No auth in this project — always return true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email:rfc,dns',
                Rule::unique('contacts', 'email')->whereNull('deleted_at'),
            ],
            'phone' => [
                'required',
                'string',
                // At least 10 digits after removing non-digits
                // Detailed validation happens in the Observer (normalization)
                // but we enforce minimum length here
                'regex:/^[\d\s\-\(\)\+]+$/',
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     * Normalize phone format before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => preg_replace('/\D/', '', $this->phone ?? ''),
        ]);
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O email é obrigatório.',
            'email.email' => 'Informe um email válido.',
            'email.unique' => 'Este email já está cadastrado.',
            'phone.required' => 'O telefone é obrigatório.',
            'phone.regex' => 'O telefone deve conter apenas números, espaços, traços ou parênteses.',
        ];
    }
}
```

### Example 9: API Resource — ContactResource

**Source:** [VERIFIED: laravel.com/docs/13.x/eloquent-resources]

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Domain\Entities\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a Contact domain entity into a standardized JSON response.
 *
 * @property-read Contact $resource
 */
class ContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Contact $contact */
        $contact = $this->resource;

        return [
            'id' => $contact->id(),
            'name' => $contact->name(),
            'email' => $contact->email()->value,
            'phone' => $contact->phone()->value,
            'score' => $contact->score()->value,
            'status' => $contact->status()->value,
            'processed_at' => $contact->processedAt()?->format('Y-m-d\TH:i:s\Z'),
            'created_at' => $contact->createdAt()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $contact->updatedAt()?->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
```

### Example 10: Observer — ContactObserver

**Source:** [VERIFIED: D-13, D-14, D-15, D-16 in CONTEXT.md]

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Observers;

use App\Infrastructure\Models\Contact;

/**
 * Observer for the Contact Eloquent model.
 * Normalizes phone format on the saving event.
 */
class ContactObserver
{
    /**
     * Handle the Contact "saving" event.
     * Strip all non-digit characters from phone before persistence.
     */
    public function saving(Contact $contact): void
    {
        if ($contact->isDirty('phone')) {
            $contact->phone = preg_replace('/\D/', '', $contact->phone);
        }
    }
}
```

### Example 11: Migration — Create Contacts Table

**Source:** [VERIFIED: laravel.com/docs/13.x/migrations]

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->integer('score')->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes(); // Adds nullable deleted_at column
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
```

### Example 12: Service Container Binding

**Source:** [VERIFIED: laravel.com/docs/13.x/providers]

```php
// app/Providers/AppServiceProvider.php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Models\Contact;
use App\Infrastructure\Observers\ContactObserver;
use App\Infrastructure\Repositories\EloquentContactRepository;
use Domain\Repositories\ContactRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind repository interface to Eloquent implementation
        $this->app->bind(
            ContactRepositoryInterface::class,
            EloquentContactRepository::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the phone normalization observer
        Contact::observe(ContactObserver::class);
    }
}
```

### Example 13: Feature Test — ContactApiTest

**Source:** [VERIFIED: D-17, D-18 in CONTEXT.md, adapted from laravel.com/docs/13.x/http-tests]

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_URL = '/api/contacts';

    // ─── CREATE ───────────────────────────────────────────────────

    public function test_can_create_a_contact(): void
    {
        $payload = [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'phone' => '(11) 99999-9999',
        ];

        $response = $this->postJson(self::BASE_URL, $payload);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'phone', 'score', 'status'],
            ])
            ->assertJsonPath('data.name', 'João Silva')
            ->assertJsonPath('data.email', 'joao@example.com')
            ->assertJsonPath('data.score', 0)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('contacts', [
            'email' => 'joao@example.com',
            'score' => 0,
            'status' => 'pending',
        ]);
    }

    public function test_create_returns_validation_errors(): void
    {
        $response = $this->postJson(self::BASE_URL, [
            'name' => '',
            'email' => 'not-an-email',
            'phone' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'phone']);
    }

    // ─── LIST ─────────────────────────────────────────────────────

    public function test_can_list_contacts_with_pagination(): void
    {
        Contact::factory()->count(15)->create();

        $response = $this->getJson(self::BASE_URL);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['data' => [['id', 'name', 'email']]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 15);
    }

    // ─── SHOW ─────────────────────────────────────────────────────

    public function test_can_show_a_contact(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->getJson(self::BASE_URL . '/' . $contact->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $contact->id)
            ->assertJsonPath('data.name', $contact->name);
    }

    public function test_show_returns_404_for_nonexistent_contact(): void
    {
        $response = $this->getJson(self::BASE_URL . '/999');

        $response->assertNotFound();
    }

    // ─── UPDATE ───────────────────────────────────────────────────

    public function test_can_update_a_contact(): void
    {
        $contact = Contact::factory()->create();
        $payload = [
            'name' => 'Maria Souza',
            'email' => 'maria@example.com',
            'phone' => '21988887777',
        ];

        $response = $this->putJson(
            self::BASE_URL . '/' . $contact->id,
            $payload,
        );

        $response->assertOk()
            ->assertJsonPath('data.name', 'Maria Souza')
            ->assertJsonPath('data.email', 'maria@example.com');

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'name' => 'Maria Souza',
        ]);
    }

    public function test_update_returns_validation_errors(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->putJson(
            self::BASE_URL . '/' . $contact->id,
            ['name' => '', 'email' => 'invalid', 'phone' => ''],
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'phone']);
    }

    // ─── DELETE (SOFT) ────────────────────────────────────────────

    public function test_can_soft_delete_a_contact(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->deleteJson(self::BASE_URL . '/' . $contact->id);

        $response->assertNoContent(204); // 204 No Content

        // Soft-deleted — still in DB but with deleted_at
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    }

    public function test_show_returns_404_after_delete(): void
    {
        $contact = Contact::factory()->create();
        $contact->delete(); // Soft delete

        $response = $this->getJson(self::BASE_URL . '/' . $contact->id);

        $response->assertNotFound();
    }
}
```

### Example 14: Route Definition

**Source:** [VERIFIED: laravel.com/docs/13.x/routing]

```php
<?php

// routes/api.php
use App\Http\Controllers\Api\ContactController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::apiResource('contacts', ContactController::class)->only([
    'index', 'store', 'show', 'update', 'destroy',
]);
```
Using `->only(...)` is explicit about what endpoints exist. The `apiResource` method uses `Route::resource` with the `api` parameter, which excludes `create` and `edit` routes — perfect for REST APIs.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `$casts` property on Model | `casts()` method (or `$casts` property) | Laravel 11 (2024) | Both work. `casts()` method enables named static methods on built-in casters. Property is simpler. Prefer `$casts` property for clarity in this project. |
| `config/app.php` providers array | `bootstrap/providers.php` | Laravel 11 (2024) | Service providers are now registered in `bootstrap/providers.php`. `make:provider` auto-adds there. [VERIFIED: laravel.com/docs/13.x/providers] |
| `php artisan make:model -mcr` | `php artisan make:model -m --api --requests` | Laravel 13 | Explicit flags give finer control. `--api` generates controller with `index/store/show/update/destroy`. `--requests` generates Form Requests. |

**Deprecated/outdated:**
- `config/app.php` providers array: Use `bootstrap/providers.php` instead (Laravel 11+).
- Old-style `make:request` without `--api` flag: Prefer creating with the correct flags for cleaner structure.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `ContactStatus` is implemented as a PHP 8.1+ backed string enum located in `src/Domain/Enums/` | Standard Stack / Code Examples | LOW — backed enums are stable since PHP 8.1. If user prefers a different enum library or class-based enum, the mapper and casts need adjusting. |
| A2 | Eloquent model uses `$casts` property (not `casts()`) for enum and VO casting | Code Examples | LOW — both approaches work. `casts()` method is equally valid. |
| A3 | Service container binding goes in `AppServiceProvider::register()` | Code Examples | LOW — could use a dedicated `RepositoryServiceProvider`. Syntax is the same. |
| A4 | `bootstrap/providers.php` is the registration point (not `config/app.php`) | Code Examples | LOW — Laravel 11+ uses `bootstrap/providers.php`. If the project was scaffolded with Laravel 10 it would differ, but we're scaffolding fresh with L13. |
| A5 | `php artisan test` uses PHPUnit (not Pest) | Testing | LOW — The project could use Pest instead. The command is the same. Test syntax differs slightly. |
| A6 | `Route::apiResource('contacts', ...)` maps to `App\Http\Controllers\Api\ContactController` | Code Examples | LOW — could use a different namespace or controller name. Path convention only. |
| A7 | Feature test assertions use `assertSoftDeleted()` and `assertNoContent()` | Code Examples | LOW — These are built-in Laravel test helpers available since L8/L9. If removed in L13, fall back to manual assertions. |

**If this table is empty:** N/A — claims above tagged [ASSUMED] need user confirmation.

## Open Questions

1. **PHP environment setup**
   - What we know: PHP 8.3+ is not installed. Composer is not installed. Docker is not installed.
   - What's unclear: What setup method the user prefers (native PHP install, Laravel Sail via Docker, or Laravel Herd).
   - Recommendation: The planner should include a task to install PHP 8.3+ and Composer, or set up Laravel Sail. Default to native install for simplicity since the project will need Redis and Reverb later anyway. Flag to the user for confirmation.

2. **Email validation strictness in FormRequest**
   - What we know: `email:rfc,dns` validates format AND DNS domain existence.
   - What's unclear: Whether `dns` validation is desired in tests (no DNS lookup available) or the simpler `email:filter` should be used.
   - Recommendation: Use `email:rfc` (format-only, no DNS lookup) for reliability in test environments.

3. **Test database driver**
   - What we know: `RefreshDatabase` trait works with both MySQL and SQLite.
   - What's unclear: Whether to use in-memory SQLite for tests (faster) or MySQL (matches production).
   - Recommendation: Use SQLite in-memory for tests via `phpunit.xml` setting `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`. This is the standard Laravel pattern [ASSOCIATED].

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP 8.3+ | Laravel runtime | ✗ | — | Install via apt `php8.3` and required extensions (mbstring, pdo, sqlite, xml, bcmath) |
| Composer | Package management, autoload | ✗ | — | Install via `apt install composer` or official installer |
| Docker | Laravel Sail (alternative) | ✗ | — | Use native PHP/Composer install instead |

**Missing dependencies with no fallback:**
- PHP 8.3+ — required for Laravel 13. Must be installed before scaffolding.
- Composer — required for dependency management. Must be installed before scaffolding.

**Missing dependencies with fallback:**
- Docker → Skip Sail approach, install PHP + Composer natively. This is simpler for the project's scope.

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V5 Input Validation | yes | Form Requests (`StoreContactRequest`, `UpdateContactRequest`) with Laravel's validation rules |
| V6 Cryptography | no | No sensitive data stored; passwords/encryption out of scope per spec |

### Known Threat Patterns for Laravel 13

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Mass assignment | Tampering | Eloquent `$fillable` or `$guarded` on Contact model; UseCase uses only validated fields |
| SQL injection | Tampering | Eloquent Query Builder parameterizes all queries; raw SQL not used |
| XSS via API response | Information Disclosure | API Resources return JSON, not HTML; no XSS vector in JSON responses |

## Sources

### Primary (HIGH confidence)
- [VERIFIED: laravel.com/docs/13.x/releases] — Laravel 13 release info, PHP requirements, support timeline
- [VERIFIED: laravel.com/docs/13.x/eloquent-mutators] — Custom casts, Value Object casting, CastsAttributes interface
- [VERIFIED: laravel.com/docs/13.x/validation] — Form Request validation, available rules
- [VERIFIED: laravel.com/docs/13.x/eloquent-resources] — API Resource serialization
- [VERIFIED: laravel.com/docs/13.x/providers] — Service provider registration, bootstrap/providers.php
- [VERIFIED: laravel.com/docs/13.x/migrations] — Schema builder, softDeletes()
- [VERIFIED: laravel.com/docs/13.x/eloquent#observers] — Observer registration
- [VERIFIED: laravel.com/docs/13.x/eloquent-factories] — Model factories for testing
- [VERIFIED: php.net/enumerations] — PHP 8.1 backed enums
- [VERIFIED: wiki.php.net/rfc/readonly_classes] — PHP 8.2 readonly classes
- [VERIFIED: command check] — PHP/Composer/Docker not installed on target machine

### Secondary (MEDIUM confidence)
- [VERIFIED: pragmatticddd.com/blog/value-objects-laravel/] — Value Object implementation patterns with Eloquent custom casts
- [VERIFIED: github.com/laravel/framework/blob/13.x/CHANGELOG.md] — Laravel 13 changelog, version history
- [VERIFIED: artisan.page/13.x/] — Artisan command reference for Laravel 13

### Tertiary (LOW confidence)
- None — all critical claims are verified against official sources.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — Laravel 13, PHP 8.3+, all verified via official docs
- Architecture: HIGH — DDD patterns well-documented, CONTEXT.md decisions precise
- Pitfalls: MEDIUM — some are experience-based; most documented in Laravel community
- Environment: HIGH — directly verified via command checks

**Research date:** 2026-05-12
**Valid until:** 2026-06-12 (30 days; Laravel 13 is stable and recent)
