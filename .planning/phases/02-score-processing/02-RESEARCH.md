# Phase 2: Score Processing — Research

**Researched:** 2026-05-18
**Domain:** Async score calculation with Strategy pattern + Queue job in Laravel DDD
**Confidence:** HIGH

## Summary

Phase 2 implements the async score processing pipeline for contacts. The architecture follows established DDD patterns from Phase 1: a **Domain Service** (`ScoreCalculator`) orchestrates individual **ScoringStrategy** implementations, an **Application Use Case** (`ProcessScoreUseCase`) drives the status machine and persistence, and a **Laravel Queue Job** (`ProcessContactScoreJob`) in Infrastructure provides the async boundary with simulated processing delay.

The key architectural insight is the clean separation: **Strategies** live in Domain (framework-agnostic), the **Use Case** orchestrates in Application, and the **Job** (sleep simulation, dispatch mechanics) lives in Infrastructure. The existing `Contact` entity already has `markAsProcessing()`, `markAsActive(Score)`, and `markAsFailed()` methods — no entity changes needed. The existing `ContactStatus` enum already validates all required state transitions.

**Primary recommendation:** Implement 3 scoring strategies (Email, Name, Phone), a `ScoreCalculator` domain service, a `ProcessScoreUseCase` in Application, a `ProcessContactScoreJob` in Infrastructure, and a `processScore()` method on the existing `ContactController`. No new packages are required — Laravel 13's built-in queue system and the existing codebase provide everything.

### Key Findings
- Existing `Contact` entity already has status transition methods (`markAsProcessing`, `markAsActive`, `markAsFailed`) — no changes needed
- No Redis PHP extension available — queue uses `database` driver (already configured in `.env`)
- Test environment uses `QUEUE_CONNECTION=sync` (already configured in `phpunit.xml`) — jobs execute inline during testing
- `Queue::fake()` lets feature tests verify dispatch without executing the sleep-heavy job
- Clean unit tests for strategies and use case are possible with pure PHPUnit (`PHPUnit\Framework\TestCase`) — no Laravel bootstrap needed

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Score calculation rules (corporate email, .br TLD, name length, phone DDD) | **Domain** | — | Pure business logic, framework-agnostic — `src/Domain/Services/Scoring/*` |
| Score calculation orchestration (Strategy pattern) | **Domain** | — | `ScoreCalculator` aggregates strategies — no framework dependencies |
| Status machine transitions | **Domain** | — | Already implemented on `Contact` entity (`markAsProcessing`, `markAsActive`, `markAsFailed`) |
| Process-score endpoint (`POST /api/contacts/{id}/process-score`) | **API / Backend** | Application | HTTP concern — controller dispatches job, returns 202 |
| Async job dispatching | **Infrastructure** | — | `ProcessContactScoreJob` — Laravel queue concern |
| Process orchestration (find → process → save) | **Application** | — | `ProcessScoreUseCase` coordinates repository + calculator |
| Score persistence | **Infrastructure** | — | Existing `EloquentContactRepository.save()` — no changes needed |
| Simulated processing delay | **Infrastructure** | — | `sleep(rand(1,2))` in job handler — infrastructure concern |

## Standard Stack

### Existing (No New Packages Needed)
| Component | What | Role |
|-----------|------|------|
| `Contact` entity | `src/Domain/Entities/Contact.php` | Status transitions already implemented |
| `ContactStatus` enum | `src/Domain/Enums/ContactStatus.php` | Validates `pending→processing→active|failed` |
| `ContactRepositoryInterface` | `src/Domain/Repositories/ContactRepositoryInterface.php` | Already injected into use cases |
| `EloquentContactRepository` | `app/Infrastructure/Repositories/EloquentContactRepository.php` | Already wired via container |
| `ContactMapper` | `app/Infrastructure/Persistence/Mappers/ContactMapper.php` | Handles domain↔Eloquent mapping |
| `AppServiceProvider` | `app/Providers/AppServiceProvider.php` | Already binds repository + observer |
| Laravel Queue | Built-in | `QUEUE_CONNECTION=database` in `.env`, `sync` in tests |
| PHPUnit 12.5 | Built-in | `QUEUE_CONNECTION=sync` in `phpunit.xml` — jobs run inline |

### New Files to Create
| File | Role | Tier |
|------|------|------|
| `src/Domain/Services/Scoring/ScoreScoringStrategy.php` | Strategy interface | Domain |
| `src/Domain/Services/Scoring/EmailDomainScoringStrategy.php` | Email rules (corporate +20, .br +10) | Domain |
| `src/Domain/Services/Scoring/NameLengthScoringStrategy.php` | Full name +10 | Domain |
| `src/Domain/Services/Scoring/PhoneDddScoringStrategy.php` | SP DDD +20, other DDD +10 | Domain |
| `src/Domain/Services/ScoreCalculator.php` | Orchestrates strategies | Domain |
| `src/Application/UseCases/ProcessScoreUseCase.php` | Processing orchestration | Application |
| `app/Jobs/ProcessContactScoreJob.php` | Async job (sleep + dispatch use case) | Infrastructure |
| `tests/Unit/Domain/Services/Scoring/EmailDomainScoringStrategyTest.php` | Strategy unit tests | Test |
| `tests/Unit/Domain/Services/Scoring/NameLengthScoringStrategyTest.php` | Strategy unit tests | Test |
| `tests/Unit/Domain/Services/Scoring/PhoneDddScoringStrategyTest.php` | Strategy unit tests | Test |
| `tests/Unit/Domain/Services/ScoreCalculatorTest.php` | Calculator unit test | Test |
| `tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php` | Use case unit test | Test |
| `tests/Feature/ScoreProcessingTest.php` | Integration tests | Test |

### Files to Modify
| File | Change | Why |
|------|--------|-----|
| `app/Http/Controllers/Api/ContactController.php` | Add `processScore()` method | New endpoint |
| `routes/api.php` | Add process-score route | Route registration |
| `app/Providers/AppServiceProvider.php` | Bind strategies + calculator | Service container wiring |

## Package Legitimacy Audit

No external packages need to be installed for this phase. Laravel 13 ships with everything required:
- Queue system (database/sync drivers)
- `Queue::fake()` for testing
- Container auto-resolution for use cases and jobs

**Disposition:** No packages to audit.

## Architecture Patterns

### System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        POST /api/contacts/{id}/process-score        │
│                              (Controller)                           │
└───────────────────────────┬─────────────────────────────────────────┘
                            │ 1. Find contact via GetContactUseCase
                            │ 2. Return 404 if not found
                            │ 3. Dispatch ProcessContactScoreJob
                            │ 4. Return 202 Accepted
                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     ProcessContactScoreJob (Infra)                   │
│                      ┌─────────────────────────┐                    │
│                      │  sleep(rand(1,2))        │  ← simulated delay │
│                      │  Call ProcessScoreUseCase│                    │
│                      └──────────┬──────────────┘                    │
└─────────────────────────────────┼───────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   ProcessScoreUseCase (Application)                  │
│                                                                      │
│  1. Find contact by ID (repository)                                  │
│  2. markAsProcessing() → save                                        │
│  3. calculator.calculate(contact) → get Score                        │
│  4. markAsActive(score) or markAsFailed($e)                          │
│  5. save (final state)                                               │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
┌─────────────────┐ ┌──────────┐ ┌──────────────────┐
│EmailDomainStrategy│ │NameLength│ │PhoneDddStrategy  │
│ corporate: +20   │ │ fullName │ │ SP DDD 11-19:+20 │
│ .br TLD: +10     │ │  +10     │ │ other DDD: +10   │
└─────────────────┘ └──────────┘ └──────────────────┘
              │            │            │
              └────────────┼────────────┘
                           │
                           ▼
              ┌─────────────────────────┐
              │  ScoreCalculator        │
              │  Σ(strategies) = total  │
              └─────────────────────────┘
```

### Recommended Project Structure (Additions Only)
```
src/
├── Domain/
│   ├── Services/
│   │   ├── Scoring/
│   │   │   ├── ScoreScoringStrategy.php          # Interface
│   │   │   ├── EmailDomainScoringStrategy.php     # CALC-01 + CALC-02
│   │   │   ├── NameLengthScoringStrategy.php      # CALC-03
│   │   │   └── PhoneDddScoringStrategy.php        # CALC-04 + CALC-05
│   │   └── ScoreCalculator.php                    # Orchestrator
│   └── ... (existing entities, VOs, enums)
├── Application/
│   ├── UseCases/
│   │   ├── ProcessScoreUseCase.php                # NEW
│   │   └── ... (existing use cases)
│   └── ...
app/
├── Jobs/
│   └── ProcessContactScoreJob.php                 # NEW
├── Http/
│   ├── Controllers/Api/ContactController.php      # MODIFIED
│   ...
└── Providers/
    └── AppServiceProvider.php                     # MODIFIED
```

### Pattern 1: Strategy Pattern for Score Calculation

**What:** A `ScoreScoringStrategy` interface defines a single `score(Contact): int` method. Each concrete strategy encapsulates one set of scoring rules. A `ScoreCalculator` aggregates all registered strategies and sums their results.

**When to use:** Any time scoring rules need to be independently added, removed, or tested without modifying existing code. This is the primary extensibility requirement (SCORE-03/CALC requirements).

**Interface:**
```php
<?php

namespace Domain\Services\Scoring;

use Domain\Entities\Contact;

interface ScoreScoringStrategy
{
    /** Calculate score contribution for a contact. Returns additional points (0+). */
    public function score(Contact $contact): int;
}
```

**Concrete strategy example:**
```php
<?php

namespace Domain\Services\Scoring;

use Domain\Entities\Contact;

final class EmailDomainScoringStrategy implements ScoreScoringStrategy
{
    private const NON_CORPORATE_DOMAINS = ['gmail', 'hotmail', 'yahoo'];

    public function score(Contact $contact): int
    {
        $points = 0;
        $email = $contact->email();

        // CALC-01: Corporate domain → +20
        $domainPrefix = explode('.', $email->domain())[0];
        if (!in_array(strtolower($domainPrefix), self::NON_CORPORATE_DOMAINS, true)) {
            $points += 20;
        }

        // CALC-02: .br TLD → +10
        if ($email->tld() === 'br') {
            $points += 10;
        }

        return $points;
    }
}
```

**Note on TLD detection edge case:** `Email::tld()` uses `end(explode('.', domain()))`. For `empresa.com.br`, the domain is `empresa.com.br`, so `parts` = `['empresa', 'com', 'br']`, `end()` = `'br'`. Correct. For `empresa.co.uk`, `end()` = `'uk'` — also correct. No edge case issues with the existing VO.

**Calculator orchestrator:**
```php
<?php

namespace Domain\Services;

use Domain\Entities\Contact;
use Domain\Services\Scoring\ScoreScoringStrategy;
use Domain\ValueObjects\Score;

final class ScoreCalculator
{
    /** @param ScoreScoringStrategy[] $strategies */
    public function __construct(
        private readonly array $strategies,
    ) {}

    /** Calculate total score based on all registered strategies. */
    public function calculate(Contact $contact): Score
    {
        $total = 0;
        foreach ($this->strategies as $strategy) {
            $total += $strategy->score($contact);
        }
        return new Score($total);
    }
}
```

### Pattern 2: Use Case + Queue Job Separation

**What:** The `ProcessScoreUseCase` (Application) handles orchestration and is framework-agnostic. The `ProcessContactScoreJob` (Infrastructure) provides the async boundary — it receives a contact ID, applies sleep simulation, then delegates to the use case.

**When to use:** Any async operation in DDD with Laravel. Keeps business logic testable without the queue.

**Job:**
```php
<?php

namespace App\Jobs;

use Application\UseCases\ProcessScoreUseCase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessContactScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $contactId,
    ) {}

    public function handle(ProcessScoreUseCase $useCase): void
    {
        // SCORE-06: Simulated processing delay
        sleep(rand(1, 2));

        $useCase->execute($this->contactId);
    }
}
```

**Use case:**
```php
<?php

namespace Application\UseCases;

use Domain\Repositories\ContactRepositoryInterface;
use Domain\Services\ScoreCalculator;

class ProcessScoreUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
        private readonly ScoreCalculator $calculator,
    ) {}

    /** @throws \DomainException on invalid status transition */
    public function execute(int $contactId): void
    {
        $contact = $this->repository->findById($contactId);
        if ($contact === null) {
            throw new \RuntimeException("Contact not found: {$contactId}");
        }

        // SCORE-02: Transition to processing
        $contact->markAsProcessing();
        $this->repository->save($contact);

        try {
            // SCORE-03, CALC-01–05: Calculate score
            $score = $this->calculator->calculate($contact);

            // SCORE-04: Success → active with score + processed_at
            $contact->markAsActive($score);
        } catch (\Throwable $e) {
            // SCORE-05: Failure → failed, score unchanged
            $contact->markAsFailed();
        }

        $this->repository->save($contact);
    }
}
```

**Controller addition:**
```php
// In ContactController:

public function processScore(int $id): JsonResponse
{
    $contact = $this->getContact->execute($id);
    if ($contact === null) {
        return response()->json(['message' => 'Not Found'], 404);
    }

    ProcessContactScoreJob::dispatch($id);

    return response()->json([
        'message' => 'Score processing queued.',
        'contact_id' => $id,
    ], 202);
}
```

**Route addition:**
```php
// In routes/api.php — add after existing apiResource:
Route::post('contacts/{id}/process-score', [ContactController::class, 'processScore']);
```

### Anti-Patterns to Avoid

- **Putting `sleep()` in the use case** — sleep is an infrastructure simulation; it belongs in the job. The use case should be testable without real delays.
- **Passing the entire `Contact` entity to the job constructor** — Jobs serialize their properties. The Contact entity may not serialize cleanly across queue drivers. Always pass the ID and fetch in `handle()`.
- **Catching exceptions silently in the use case** — `markAsFailed()` should only catch expected errors. Let unexpected exceptions propagate for the queue retry mechanism.
- **Putting domain events in Phase 2** — Events (`ContactScoreProcessed`) are Phase 3. Phase 2 should NOT dispatch domain events to keep scope clean.

### Architecture Decision: Strategy Takes Contact Entity

Strategies receive the full `Contact` entity rather than primitives. Rationale:
- Strategies in the Domain layer can depend on Domain entities (same layer)
- Access to all VOs (`Email`, `Phone`, etc.) provides type-safe data access
- New strategies in the future can use any Contact attribute without signature changes
- Keeps strategy interface stable: `score(Contact $contact): int`

### Architecture Decision: Score Calculation Strategy Granularity

**Chosen:** One strategy per "domain area" (Email, Name, Phone) rather than one per scoring rule (corporate email, .br TLD, name length, SP DDD, other DDD).

Rationale: Having 5 separate strategies is unnecessarily fine-grained for rules that always co-occur within the same domain area. The email rules (CALC-01 + CALC-02) and phone rules (CALC-04 + CALC-05) are naturally grouped. New rules can always be split into a new strategy class when they represent a conceptually independent concern.

### Service Container Wiring

```php
// In AppServiceProvider::register():

$this->app->bind(ScoreCalculator::class, function ($app) {
    return new ScoreCalculator([
        $app->make(EmailDomainScoringStrategy::class),
        $app->make(NameLengthScoringStrategy::class),
        $app->make(PhoneDddScoringStrategy::class),
    ]);
});

// ProcessScoreUseCase is auto-resolved by the container
// ProcessContactScoreJob's handle() method uses method injection
```

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Queue management | Custom queue worker | Laravel's `queue:work` | Retries, timeouts, failed job tables, driver abstraction |
| Async job dispatch | Manual DB polling | `ShouldQueue` + `dispatch()` | Built-in serialization, sync driver for tests |
| Status machine logic | Complex if/else chains | Existing `ContactStatus::canTransitionTo()` | Already implemented and tested in Phase 1 |

**Key insight:** Laravel's built-in queue system handles all the async infrastructure. The DDD architecture adds domain purity — the job itself is just a thin adapter that delegates to the application use case.

## Common Pitfalls

### Pitfall 1: Job Serialization with Domain Entities
**What goes wrong:** Passing a `Contact` entity to a queue job constructor. When the job is pushed to a non-sync queue (database/redis), it serializes. The entity may not serialize properly (private properties, `DateTimeImmutable`, etc.).
**How to avoid:** Always pass `int $contactId` and fetch in `handle()`.
**Warning signs:** "Serialization of Closure is not allowed" or `DateTimeImmutable` serialization errors.

### Pitfall 2: Sleep Making Tests Slow
**What goes wrong:** The `sleep(rand(1,2))` in the job handler also runs during feature tests with `QUEUE_CONNECTION=sync`, making tests unpredictably slow (1-2 seconds per test).
**How to avoid:** Feature tests use `Queue::fake()` to assert dispatch only. Unit tests for the use case have no sleep (it's not in the use case). The sleep is exclusively in the job, which is infrastructure.
**Warning signs:** Feature tests taking >1 second per test for simple assertions.

### Pitfall 3: Double-Saving vs Transaction Boundary
**What goes wrong:** The use case saves twice (processing → save, active/failed → save). If the second save fails, the contact is stuck in `processing` status permanently.
**How to avoid:** For this phase, the double-save pattern is intentional — it ensures `processing` status is visible during the async window. In a production system, you'd wrap both saves in a database transaction. Acceptable for MVP.

### Pitfall 4: Missing Repository Save After Status Change
**What goes wrong:** The entity's status is changed in memory but the repository's `save()` is never called.
**How to avoid:** Always call `$this->repository->save($contact)` after any status transition. The use case explicitly calls save twice (after processing, after active/failed).

### Pitfall 5: Controller Checking Contact Exists vs Using Route Model Binding
**What goes wrong:** If we use explicit route model binding (`{contact}` instead of `{id}`), Laravel auto-fetches the model, including soft-deleted ones. The existing pattern uses `int $id` + use case lookup.
**How to avoid:** Follow the existing pattern: `int $id` parameter, use `GetContactUseCase` to check existence. This keeps consistency with the rest of the controller.

## Code Examples

### Verified Pattern: Use Case + Repository Injection

```php
// Source: Existing codebase (src/Application/UseCases/CreateContactUseCase.php)
// Application layer use case with constructor injection of repository interface.
// Framework-agnostic — no Laravel imports.

class CreateContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
    ) {}

    public function execute(string $name, string $email, string $phone): Contact
    {
        $emailVO = new Email($email);
        $phoneVO = new Phone($phone);
        $contact = Contact::create($name, $emailVO, $phoneVO);
        $this->repository->save($contact);
        return $contact;
    }
}
```

### Verified Pattern: Controller Structure

```php
// Source: Existing codebase (app/Http/Controllers/Api/ContactController.php)
// Controller uses constructor injection for all use cases.
// Methods delegate entirely to use cases — no business logic.

class ContactController extends Controller
{
    public function __construct(
        private readonly CreateContactUseCase $createContact,
        private readonly ListContactsUseCase $listContacts,
        private readonly GetContactUseCase $getContact,
        private readonly UpdateContactUseCase $updateContact,
        private readonly DeleteContactUseCase $deleteContact,
    ) {}
    // ...
}
```

### Verified Pattern: API Resource

```php
// Source: Existing codebase (app/Http/Resources/ContactResource.php)
// Resource maps domain entity to JSON structure.

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id(),
            'name' => $this->resource->name(),
            'email' => $this->resource->email()->value,
            'phone' => $this->resource->phone()->value,
            'score' => $this->resource->score()->value,
            'status' => $this->resource->status()->value,
            'processed_at' => $this->resource->processedAt()?->format('Y-m-d\TH:i:s\Z'),
            'created_at' => $this->resource->createdAt()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->resource->updatedAt()?->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
```

### Test Pattern: Unit Test for Strategy

```php
// Source: Standard PHPUnit practice
// Pure unit test — no Laravel bootstrap. Tests PHPUnit\Framework\TestCase.

use PHPUnit\Framework\TestCase;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;
use Domain\Entities\Contact;

class EmailDomainScoringStrategyTest extends TestCase
{
    private EmailDomainScoringStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new EmailDomainScoringStrategy();
    }

    public function test_corporate_email_adds_20_points(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@company.com'),
            new Phone('11999999999'),
        );

        $points = $this->strategy->score($contact);

        $this->assertSame(20, $points);
    }

    public function test_br_tld_adds_10_points(): void
    {
        $contact = Contact::create(
            'John',
            new Email('john@gmail.com.br'),
            new Phone('11999999999'),
        );

        $points = $this->strategy->score($contact);

        $this->assertSame(10, $points); // not corporate (gmail) but .br
    }

    public function test_gmail_email_no_points(): void
    {
        $contact = Contact::create(
            'John',
            new Email('john@gmail.com'),
            new Phone('11999999999'),
        );

        $points = $this->strategy->score($contact);

        $this->assertSame(0, $points);
    }
}
```

### Test Pattern: Unit Test for Use Case

```php
// Source: Standard PHPUnit + Mockery practice
// Pure unit test — mocks infrastructure.

use PHPUnit\Framework\TestCase;
use Domain\Entities\Contact;

class ProcessScoreUseCaseTest extends TestCase
{
    public function test_processes_score_successfully(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@corp.com.br'),
            new Phone('11999999999'),
        );

        $repository = $this->createMock(ContactRepositoryInterface::class);
        $repository->method('findById')->willReturn($contact);

        $calculator = $this->createMock(ScoreCalculator::class);
        $calculator->method('calculate')->willReturn(new Score(60));

        $useCase = new ProcessScoreUseCase($repository, $calculator);
        $useCase->execute(1);

        $this->assertSame(ContactStatus::Active, $contact->status());
        $this->assertSame(60, $contact->score()->value);
        $this->assertNotNull($contact->processedAt());
    }

    public function test_fails_on_exception(): void
    {
        $contact = Contact::create('John', new Email('john@test.com'), new Phone('21988887777'));

        $repository = $this->createMock(ContactRepositoryInterface::class);
        $repository->method('findById')->willReturn($contact);

        $calculator = $this->createMock(ScoreCalculator::class);
        $calculator->method('calculate')->willThrowException(new \RuntimeException('Calc error'));

        $useCase = new ProcessScoreUseCase($repository, $calculator);
        $useCase->execute(1);

        $this->assertSame(ContactStatus::Failed, $contact->status());
        $this->assertNotNull($contact->processedAt());
        $this->assertSame(0, $contact->score()->value); // unchanged
    }
}
```

### Test Pattern: Feature Test for Process Endpoint

```php
// Source: Existing pattern from tests/Feature/ContactApiTest.php
// Uses RefreshDatabase, Contact::factory(), and Queue::fake()

use Illuminate\Support\Facades\Queue;

class ScoreProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_score_returns_202_and_dispatches_job(): void
    {
        Queue::fake();

        $contact = Contact::factory()->create(['status' => 'pending']);

        $response = $this->postJson("/api/contacts/{$contact->id}/process-score");

        $response->assertStatus(202);
        $response->assertJson([
            'message' => 'Score processing queued.',
            'contact_id' => $contact->id,
        ]);

        Queue::assertPushed(ProcessContactScoreJob::class, function ($job) use ($contact) {
            return $job->contactId === $contact->id;
        });
    }

    public function test_process_score_returns_404_for_nonexistent(): void
    {
        $response = $this->postJson('/api/contacts/999/process-score');
        $response->assertStatus(404);
    }

    public function test_process_score_with_sync_queue_full_integration(): void
    {
        // Without Queue::fake(), sync driver runs the job immediately
        $contact = Contact::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@company.com',
            'phone' => '11999999999',
            'status' => 'pending',
            'score' => 0,
        ]);

        $this->postJson("/api/contacts/{$contact->id}/process-score");

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'status' => 'active',
            'score' => 50, // 20(corp) + 10(multi-word) + 20(SP DDD)
        ]);
    }
}
```

**Note on the full integration test:** The `sync` queue driver causes `sleep(rand(1,2))` to execute inline. This test will take 1–2 seconds. For CI speed, use `Queue::fake()` variant. Both approaches are valid.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Laravel 11 queue config | Laravel 13 queue config | Laravel 13 | Same API — `ShouldQueue`, `dispatch()`, `Queue::fake()` unchanged |
| PHPUnit 10 | PHPUnit 12.5 | Laravel 13 | `createMock()` still works. `setUp()` signatures unchanged |

**Deprecated/outdated:**
- `php artisan make:job` still works in Laravel 13 — use it for scaffolding
- `dispatch()` helper function is still the standard

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Strategies receive `Contact` entity (not primitives) | Architecture Patterns | Low — strategies in Domain can depend on Domain entities. Interface can be changed later |
| A2 | `Queue::fake()` + sync driver both work identically in Laravel 13 | Code Examples | Low — this is stable API since Laravel 5.5 |
| A3 | `new \DateTimeImmutable()` creates current timestamp | Code Examples | Low — this is standard PHP behavior |
| A4 | The `ProcessScoreUseCase::execute()` should throw on missing contact | Code Examples | Low — caller (job) would fail and retry. Alternative: return null silently. Throw is clearer. |

## Open Questions (RESOLVED)

1. **Should the `ProcessScoreUseCase` dispatch domain events?**
   - What we know: Phase 3 handles `ContactScoreProcessed` event + broadcasting
   - What's unclear: If the use case should fire events now (for future listeners) or leave that entirely to Phase 3
   - Recommendation: **Do NOT fire events in Phase 2** — keep scope clean. Phase 3 will add event dispatching, possibly in the job after the use case call, or via an after-commit callback.
   — RESOLVED: Phase 2 does NOT dispatch domain events. Deferred to Phase 3 as planned.

2. **Should the use case validate that the contact is `pending` before processing?**
   - What we know: `Contact::markAsProcessing()` calls `assertTransition()` which throws `DomainException` if transition is invalid
   - What's unclear: Whether to add a pre-check with a user-friendly error message vs letting the domain exception propagate (shown as 500)
   - Recommendation: Let the domain exception propagate — it's a developer error to trigger processing on an already-processed contact. The controller already checks existence via `GetContactUseCase`.
   — RESOLVED: No pre-check. Domain exception from `assertTransition()` propagates naturally. Accepted for MVP.

3. **Should the controller validate status is `pending` before dispatching the job?**
   - What we know: The spec doesn't specify behavior for re-processing
   - What's unclear: Whether the controller should pre-check or let the job fail
   - Recommendation: For MVP, let the job handle it (status machine enforces valid transitions). The failed job will be visible in the failed_jobs table.
   — RESOLVED: Controller does not pre-check. Status machine in the entity handles invalid transitions. Failed jobs visible in failed_jobs table.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP 8.3 | Runtime | ✓ | 8.3.6 | — |
| Laravel 13 | Framework | ✓ | 13.8.0 | — |
| SQLite | Database (test) | ✓ | — | MySQL/MariaDB in production |
| Queue driver | Async jobs | ✓ | `database` (env), `sync` (test) | Redis not available — `database` driver works |
| Redis PHP extension | Redis queue | ✗ | — | `database` queue driver is current default |

**Missing dependencies with no fallback:**
- None — the project uses `QUEUE_CONNECTION=database` in `.env` and `sync` in tests. Redis is not required.

**Missing dependencies with fallback:**
- Redis extension (for queue): Not installed. Falling back to `database` queue driver (already configured). The `database` driver persists jobs in the `jobs` table and a worker processes them via `php artisan queue:work`.

## Validation Architecture

Nyquist validation enabled — include this section.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 12.5.24 |
| Config file | `phpunit.xml` (exists at root) |
| Quick run command | `php artisan test --filter=ScoreProcessingTest` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SCORE-01 | POST process-score returns 202 | feature | `php artisan test --filter=test_process_score_returns_202` | ❌ Wave 0 |
| SCORE-02 | Job transitions pending→processing→active|failed | unit | `php artisan test --filter=ProcessScoreUseCaseTest` | ❌ Wave 0 |
| SCORE-03 | Score calculated via Strategy pattern | unit | `php artisan test --filter=...ScoringStrategyTest` | ❌ Wave 0 |
| SCORE-04 | Active status + score + processed_at | unit | `php artisan test --filter=test_processes_score_successfully` | ❌ Wave 0 |
| SCORE-05 | Failed status on error | unit | `php artisan test --filter=test_fails_on_exception` | ❌ Wave 0 |
| SCORE-06 | Job has sleep(1-2) | code review | Manual check of `ProcessContactScoreJob` | ❌ Wave 0 |
| CALC-01 | Corporate email +20 | unit | `php artisan test --filter=EmailDomainScoringStrategyTest` | ❌ Wave 0 |
| CALC-02 | .br TLD +10 | unit | Same as CALC-01 test class | ❌ Wave 0 |
| CALC-03 | Full name +10 | unit | `php artisan test --filter=NameLengthScoringStrategyTest` | ❌ Wave 0 |
| CALC-04 | SP DDD 11-19 +20 | unit | `php artisan test --filter=PhoneDddScoringStrategyTest` | ❌ Wave 0 |
| CALC-05 | Other DDD +10 | unit | Same as CALC-04 test class | ❌ Wave 0 |
| TEST-01 | Domain entity/VO unit tests | unit | — | Already exists from Phase 1 |
| TEST-02 | Application use case unit tests | unit | `php artisan test --filter=ProcessScoreUseCaseTest` | ❌ Wave 0 |
| TEST-03 | Scoring strategy unit tests | unit | `php artisan test --filter=...ScoringStrategyTest` | ❌ Wave 0 |
| TEST-05 | Score processing feature tests | feature | `php artisan test --filter=ScoreProcessingTest` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --filter=ScoreProcessingTest`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `tests/Unit/Domain/Services/Scoring/EmailDomainScoringStrategyTest.php` — covers CALC-01, CALC-02
- [ ] `tests/Unit/Domain/Services/Scoring/NameLengthScoringStrategyTest.php` — covers CALC-03
- [ ] `tests/Unit/Domain/Services/Scoring/PhoneDddScoringStrategyTest.php` — covers CALC-04, CALC-05
- [ ] `tests/Unit/Domain/Services/ScoreCalculatorTest.php` — integration of strategies
- [ ] `tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php` — covers SCORE-02, SCORE-04, SCORE-05, TEST-02
- [ ] `tests/Feature/ScoreProcessingTest.php` — covers SCORE-01, TEST-05

*(No gaps for TEST-01 and TEST-04 — they exist from Phase 1)*

## Security Domain

Security enforcement is enabled (config key absent — treats as enabled).

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No | Phase involves no auth — all endpoints are public per spec |
| V3 Session Management | No | No sessions required |
| V4 Access Control | No | No user model exists |
| V5 Input Validation | Yes | Existing `StoreContactRequest` + `UpdateContactRequest` (Form Requests) |
| V6 Cryptography | No | No sensitive data at rest |
| V8 Data Protection | Partial | Soft deletes already implemented. Score data is business logic, not PII |

### Known Threat Patterns for {Laravel DDD API}

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Mass assignment via queue job | Tampering | Jobs accept only `contactId` (int) — no user-controllable fields pass through the job |
| Status machine bypass | Elevation of Privilege | `Contact::assertTransition()` validates every transition — cannot skip states |
| Job retry on domain exception | Denial of Service | Laravel's queue retry mechanism; `$tries` should be limited (default: no limit). Consider adding `public $tries = 1;` or `public $maxExceptions = 1;` to the job for scoring failures |
| Race condition (double trigger) | Tampering | Status machine prevents `active→processing`. Second trigger fails safely via `DomainException` |

**Key security note:** The process-score endpoint is unauthenticated (matching the CRUD endpoints). If authentication is added later, it applies to all routes uniformly.

## Sources

### Primary (HIGH confidence)
- [Existing codebase] — Full analysis of files at `src/Domain/`, `src/Application/`, `app/Infrastructure/`, `app/Http/`, `tests/`
- [phpunit.xml] — Confirmed `QUEUE_CONNECTION=sync` in test environment
- [composer.json] — Confirmed `laravel/framework: ^13.7`, `phpunit/phpunit: ^12.5`

### Secondary (MEDIUM confidence)
- [Laravel 13 Queue Docs] — Standard patterns verified against existing codebase implementation
- [PHPUnit 12 Manual] — `createMock()`, `assertSame()`, `setUp()` patterns

### Tertiary (LOW confidence)
- None — all findings verified against the actual codebase

## Metadata

**Confidence breakdown:**
- **Standard stack:** HIGH — verified by examining every relevant file in the codebase
- **Architecture:** HIGH — Strategy pattern, use case pattern, job pattern all follow established DDD conventions from Phase 1
- **Pitfalls:** HIGH — based on common queue job issues in Laravel DDD projects

**Research date:** 2026-05-18
**Valid until:** 2026-06-18 (stable — Laravel 13 queue API is mature)
