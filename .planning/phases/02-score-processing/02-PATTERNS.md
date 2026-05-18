# Phase 2: Score Processing — Pattern Map

**Mapped:** 2026-05-18
**Files analyzed:** 20 (12 new, 3 modified, 5 existing analogs)
**Analogs found:** 17 / 20

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `src/Domain/Services/Scoring/ScoreScoringStrategy.php` | domain-interface | transform | `src/Domain/Repositories/ContactRepositoryInterface.php` | role-match |
| `src/Domain/Services/Scoring/EmailDomainScoringStrategy.php` | domain-service | transform | `src/Domain/ValueObjects/Score.php` (final readonly pattern) | partial |
| `src/Domain/Services/Scoring/NameLengthScoringStrategy.php` | domain-service | transform | `src/Domain/ValueObjects/Score.php` (final readonly pattern) | partial |
| `src/Domain/Services/Scoring/PhoneDddScoringStrategy.php` | domain-service | transform | `src/Domain/ValueObjects/Score.php` (final readonly pattern) | partial |
| `src/Domain/Services/ScoreCalculator.php` | domain-service | transform | `src/Application/UseCases/CreateContactUseCase.php` (constructor DI pattern) | cross-role |
| `src/Application/UseCases/ProcessScoreUseCase.php` | use-case | CRUD | `src/Application/UseCases/CreateContactUseCase.php` | exact |
| `app/Jobs/ProcessContactScoreJob.php` | job | event-driven | — no analog (first job) — use RESEARCH.md pattern | none |
| `app/Http/Controllers/Api/ContactController.php` [MODIFY] | controller | request-response | Existing methods in same file (`show()`, `store()`) | exact |
| `routes/api.php` [MODIFY] | route | request-response | Existing `Route::apiResource` in same file | exact |
| `app/Providers/AppServiceProvider.php` [MODIFY] | config | — | Existing `bind()` calls in same file | exact |
| `tests/Unit/Domain/Services/Scoring/EmailDomainScoringStrategyTest.php` | test | unit | `tests/Feature/ContactApiTest.php` (PHPUnit structure) | cross-role |
| `tests/Unit/Domain/Services/Scoring/NameLengthScoringStrategyTest.php` | test | unit | Same as above | cross-role |
| `tests/Unit/Domain/Services/Scoring/PhoneDddScoringStrategyTest.php` | test | unit | Same as above | cross-role |
| `tests/Unit/Domain/Services/ScoreCalculatorTest.php` | test | unit | Same as above | cross-role |
| `tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php` | test | unit | Same as above | cross-role |
| `tests/Feature/ScoreProcessingTest.php` | test | feature | `tests/Feature/ContactApiTest.php` | exact |

## Pattern Assignments

---

### `src/Domain/Services/Scoring/ScoreScoringStrategy.php` (domain-interface, transform)

**Analog:** `src/Domain/Repositories/ContactRepositoryInterface.php` (lines 1-14)

**Imports & namespace pattern** (lines 1-8):
```php
<?php

namespace Domain\Repositories;

use Domain\Entities\Contact;

interface ContactRepositoryInterface
{
```

**Key takeaway for Strategy interface:** Use `namespace Domain\Services\Scoring;`. The interface must import `Domain\Entities\Contact` for the `score(Contact $contact): int` method signature. Follow the same single-method interface style.

**Auth pattern:** None — domain interfaces are framework-agnostic, no auth.

**Core pattern** — follow the interface contract structure:
```php
interface ContactRepositoryInterface
{
    public function save(Contact $contact): void;
    public function findById(int $id): ?Contact;
    /** @return Contact[] */
    public function findAll(int $perPage = 15, int $page = 1): array;
    public function delete(int $id): void;
}
```

**Pattern for the new interface:**
```php
interface ScoreScoringStrategy
{
    /** Calculate score contribution for a contact. Returns additional points (0+). */
    public function score(Contact $contact): int;
}
```

---

### `src/Domain/Services/Scoring/EmailDomainScoringStrategy.php` (domain-service, transform)

**Analog:** `src/Domain/ValueObjects/Score.php` (lines 1-27) — for `final readonly class` + constructor validation pattern

**Imports pattern:**
```php
<?php

namespace Domain\ValueObjects;

final readonly class Score
{
```

**Key takeaway:** Strategies are NOT readonly (they have no state, no constructor injection needed). Use `final class` without readonly. Import `Domain\Entities\Contact` and `Domain\Services\Scoring\ScoreScoringStrategy`.

**Core pattern** — implement the interface:
```php
// From Score.php — validation in constructor pattern:
public function __construct(int $value)
{
    if ($value < 0) {
        throw new \InvalidArgumentException("Score cannot be negative: {$value}");
    }
    $this->value = $value;
}
```

**New strategy pattern:**
```php
final class EmailDomainScoringStrategy implements ScoreScoringStrategy
{
    private const NON_CORPORATE_DOMAINS = ['gmail', 'hotmail', 'yahoo'];

    public function score(Contact $contact): int
    {
        $points = 0;
        $email = $contact->email();
        // ... rules using $email->domain(), $email->tld()
        return $points;
    }
}
```

---

### `src/Domain/Services/ScoreCalculator.php` (domain-service, transform)

**Analog:** `src/Application/UseCases/CreateContactUseCase.php` (lines 1-28) — for constructor dependency injection + `execute()` orchestration pattern

**Imports & constructor injection pattern** (lines 10-15):
```php
<?php

namespace Application\UseCases;

use Domain\Entities\Contact;
use Domain\Repositories\ContactRepositoryInterface;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;

class CreateContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
    ) {
    }
```

**New Calculator pattern** — aggregate strategies via constructor:
```php
final class ScoreCalculator
{
    /** @param ScoreScoringStrategy[] $strategies */
    public function __construct(
        private readonly array $strategies,
    ) {}

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

---

### `src/Application/UseCases/ProcessScoreUseCase.php` (use-case, CRUD)

**Analog:** `src/Application/UseCases/CreateContactUseCase.php` (lines 1-28) — exact role match, same data flow pattern

**Imports pattern** (lines 1-8):
```php
<?php

namespace Application\UseCases;

use Domain\Entities\Contact;
use Domain\Repositories\ContactRepositoryInterface;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;
```

**New use case imports:**
```php
namespace Application\UseCases;

use Domain\Repositories\ContactRepositoryInterface;
use Domain\Services\ScoreCalculator;
```

**Constructor injection pattern** (lines 10-15):
```php
class CreateContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
    ) {
    }
```

**New use case constructor:** inject both `ContactRepositoryInterface` and `ScoreCalculator`:
```php
class ProcessScoreUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
        private readonly ScoreCalculator $calculator,
    ) {}
```

**Core CRUD pattern** — `execute()` method with domain entity manipulation (from `CreateContactUseCase` lines 17-27):
```php
    public function execute(string $name, string $email, string $phone): Contact
    {
        $emailVO = new Email($email);
        $phoneVO = new Phone($phone);

        $contact = Contact::create($name, $emailVO, $phoneVO);

        $this->repository->save($contact);

        return $contact;
    }
```

**New use case execute pattern:**
```php
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
            $score = $this->calculator->calculate($contact);
            $contact->markAsActive($score);
        } catch (\Throwable $e) {
            $contact->markAsFailed();
        }

        $this->repository->save($contact);
    }
```

**Error handling pattern:** Use `try/catch` with `\Throwable` to catch both exceptions and errors. `markAsFailed()` is the safe path. Let unexpected exceptions propagate for queue retry.

---

### `app/Jobs/ProcessContactScoreJob.php` (job, event-driven)

**Analog:** No existing job in codebase. Use RESEARCH.md pattern (lines 250-279).

**Imports & class pattern:**
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
```

**Constructor** — only `int $contactId` (never pass domain entities):
```php
    public function __construct(
        public readonly int $contactId,
    ) {}
```

**Handle method** — method injection for use case, sleep simulation:
```php
    public function handle(ProcessScoreUseCase $useCase): void
    {
        sleep(rand(1, 2));  // SCORE-06: simulated processing delay
        $useCase->execute($this->contactId);
    }
```

---

### `app/Http/Controllers/Api/ContactController.php` [MODIFY] (controller, request-response)

**Analog:** Existing `show()` method in same file (lines 70-79) — same existence-check + JSON response pattern

**Existing pattern** — `show()` uses `GetContactUseCase` for existence check:
```php
    public function show(int $id): JsonResponse
    {
        $contact = $this->getContact->execute($id);

        if ($contact === null) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return ContactResource::make($contact)->response();
    }
```

**New `processScore()` method pattern:**
```php
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

**Import to add** at top of file (alongside existing imports):
```php
use App\Jobs\ProcessContactScoreJob;
```

**No new request class needed** — the endpoint takes only a path parameter (contact ID).

---

### `routes/api.php` [MODIFY] (route, request-response)

**Analog:** Existing route in same file (line 6)

**Existing pattern:**
```php
Route::apiResource('contacts', ContactController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
```

**New route** — add after the `apiResource` line:
```php
Route::post('contacts/{id}/process-score', [ContactController::class, 'processScore']);
```

---

### `app/Providers/AppServiceProvider.php` [MODIFY] (config, —)

**Analog:** Existing `register()` method in same file (lines 13-18)

**Existing binding pattern:**
```php
    public function register(): void
    {
        $this->app->bind(
            ContactRepositoryInterface::class,
            EloquentContactRepository::class,
        );
    }
```

**New bindings to add** inside `register()`:
```php
        $this->app->bind(ScoreCalculator::class, function ($app) {
            return new ScoreCalculator([
                $app->make(EmailDomainScoringStrategy::class),
                $app->make(NameLengthScoringStrategy::class),
                $app->make(PhoneDddScoringStrategy::class),
            ]);
        });

        // ProcessScoreUseCase and strategies are auto-resolved by the container
```

**Imports to add:**
```php
use Domain\Services\ScoreCalculator;
use Domain\Services\Scoring\EmailDomainScoringStrategy;
use Domain\Services\Scoring\NameLengthScoringStrategy;
use Domain\Services\Scoring\PhoneDddScoringStrategy;
```

---

### `tests/Unit/Domain/Services/Scoring/*StrategyTest.php` (test, unit)

**Analog pattern:** Pure PHPUnit — no Laravel bootstrap. `PHPUnit\Framework\TestCase`. Use `setUp()` to instantiate strategy.

**Pattern from RESEARCH.md (lines 499-553):**
```php
<?php

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
}
```

**Key testing patterns:**
- Use `Contact::create()` static factory to build test entities
- Domain VOs are value objects — construct inline: `new Email(...)`, `new Phone(...)`
- Assert with `$this->assertSame(expected, actual)`
- No mock needed — strategies are pure functions

---

### `tests/Unit/Domain/Services/ScoreCalculatorTest.php` (test, unit)

**Analog pattern:** Similar to use case test — uses `createMock()` for strategy interface.

**Pattern:**
```php
class ScoreCalculatorTest extends TestCase
{
    public function test_calculates_sum_of_all_strategies(): void
    {
        $contact = Contact::create('John', new Email('john@test.com'), new Phone('11999999999'));

        $strategy1 = $this->createMock(ScoreScoringStrategy::class);
        $strategy1->method('score')->willReturn(20);
        $strategy2 = $this->createMock(ScoreScoringStrategy::class);
        $strategy2->method('score')->willReturn(10);

        $calculator = new ScoreCalculator([$strategy1, $strategy2]);

        $score = $calculator->calculate($contact);

        $this->assertSame(30, $score->value);
    }
}
```

---

### `tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php` (test, unit)

**Analog:** RESEARCH.md lines 557-605 — `createMock()` for both repository and calculator.

**Pattern:**
```php
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
        $this->assertSame(0, $contact->score()->value);
    }
}
```

---

### `tests/Feature/ScoreProcessingTest.php` (test, feature)

**Analog:** `tests/Feature/ContactApiTest.php` (lines 1-177) — same `RefreshDatabase` trait, `Contact::factory()`, `$this->postJson()` pattern.

**Existing test pattern** (lines 1-13):
```php
<?php

namespace Tests\Feature;

use App\Infrastructure\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    const BASE_URL = '/api/contacts';
```

**New test pattern** — use `Queue::fake()` + assert dispatch:
```php
<?php

namespace Tests\Feature;

use App\Jobs\ProcessContactScoreJob;
use App\Infrastructure\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

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

---

## Shared Patterns

### Domain Entity Access (used by all Strategy classes)
**Source:** `src/Domain/Entities/Contact.php` methods — `email()` returns `Email`, `phone()` returns `Phone`, `name()` returns `string`
**Apply to:** All strategy classes

```php
$email = $contact->email();      // Returns Email VO
$phone = $contact->phone();      // Returns Phone VO
$name = $contact->name();       // Returns string
$domain = $email->domain();      // Returns string
$tld = $email->tld();            // Returns string
$ddd = $phone->ddd();            // Returns string
```

### Constructor Injection (use case, calculator)
**Source:** `src/Application/UseCases/CreateContactUseCase.php` lines 10-15, `src/Domain/ValueObjects/Score.php` lines 5-16
**Apply to:** `ProcessScoreUseCase`, `ScoreCalculator`

All use cases use `private readonly` promoted properties in constructor. Domain services should follow the same pattern. `ScoreCalculator` uses `array` type hint for strategies collection.

### JSON Response Format (controller)
**Source:** `app/Http/Controllers/Api/ContactController.php` methods
**Apply to:** New `processScore()` method

| Response | Pattern | Status Code |
|----------|---------|-------------|
| Success (queued) | `response()->json(['message' => '...', 'contact_id' => $id], 202)` | 202 |
| Not Found | `response()->json(['message' => 'Not Found'], 404)` | 404 |

### Service Container Binding (primary wiring)
**Source:** `app/Providers/AppServiceProvider.php` lines 13-18
**Apply to:** `ScoreCalculator`, strategies

Use `$this->app->bind(Class::class, \Closure)` for classes that need custom construction (like `ScoreCalculator` needing strategy list). Auto-resolution works for classes with simple constructor dependencies.

### Queue Driver Configuration
**Source:** `phpunit.xml` line 31
**Apply to:** All feature tests

```xml
<env name="QUEUE_CONNECTION" value="sync"/>
```

Tests run with `sync` driver — jobs execute immediately. Use `Queue::fake()` to prevent actual job execution (especially for sleep-heavy jobs).

---

## No Analog Found

Files with no close match in the codebase (planner should use RESEARCH.md patterns instead):

| File | Role | Data Flow | Reason |
|------|------|-----------|--------|
| `app/Jobs/ProcessContactScoreJob.php` | job | event-driven | No existing jobs in the codebase — first async job. Use RESEARCH.md lines 250-279 for pattern. |

## Metadata

**Analog search scope:** `src/Domain/`, `src/Application/`, `app/Http/`, `app/Infrastructure/`, `app/Providers/`, `routes/`, `tests/`, `database/factories/`, `phpunit.xml`
**Files scanned:** 25
**Pattern extraction date:** 2026-05-18
