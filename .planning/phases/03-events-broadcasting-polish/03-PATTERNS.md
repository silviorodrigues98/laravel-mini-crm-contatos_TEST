# Phase 3: Events, Broadcasting & Polish — Pattern Map

**Mapped:** 2026-05-18
**Files analyzed:** 13 (6 new, 7 modified)
**Analogs found:** 12 / 13 (1 new file has no close analog: Event and Listener classes)

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `app/Events/ContactScoreProcessed.php` | event | event-driven | `src/Application/UseCases/CreateContactUseCase.php` | partial — returns entity from method |
| `app/Listeners/LogContactScoreProcessed.php` | listener | event-driven | `app/Infrastructure/Observers/ContactObserver.php` | role-match — single-method handler |
| `routes/channels.php` | route | config | `routes/api.php` | role-match |
| `tests/Unit/Infrastructure/Events/ContactScoreProcessedTest.php` | test | event-driven | `tests/Feature/ScoreProcessingTest.php` | data-flow match — Event::fake pattern |
| `tests/Unit/Infrastructure/Listeners/LogContactScoreProcessedTest.php` | test | event-driven | `tests/Feature/ContactApiTest.php` | role-match — test class structure |
| `tests/Feature/ObserverTest.php` | test | CRUD | `tests/Feature/ContactApiTest.php` | exact — same role + data flow |
| `src/Application/UseCases/ProcessScoreUseCase.php` (MODIFY) | use-case | CRUD | `src/Application/UseCases/CreateContactUseCase.php` | exact — same role, returns Contact |
| `app/Jobs/ProcessContactScoreJob.php` (MODIFY) | job | event-driven | `app/Jobs/ProcessContactScoreJob.php` | itself — modify existing |
| `config/logging.php` (MODIFY) | config | config | `config/logging.php` | itself — add channel |
| `.env` (MODIFY) | config | config | `.env` | itself |
| `README.md` (MODIFY) | docs | docs | `README.md` | itself |
| `tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php` (MODIFY) | test | CRUD | itself | itself |
| `tests/Feature/ScoreProcessingTest.php` (MODIFY) | test | CRUD | itself | itself |

## Pattern Assignments

### `app/Events/ContactScoreProcessed.php` (event, event-driven)

**Analog:** `src/Application/UseCases/CreateContactUseCase.php` (partial match — returns data for event construction)
**Analog pattern also from:** RESEARCH.md §Architecture Patterns — no existing event in codebase

**Imports pattern** — RESEARCH.md provided reference:

```php
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
```

**Core pattern** — RESEARCH.md Pattern 1 (lines 218-255):
```php
class ContactScoreProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $contactId,
        public string $email,
        public int $score,
        public string $status,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('contacts.' . $this->contactId);
    }

    public function broadcastWith(): array
    {
        return [
            'contact_id' => $this->contactId,
            'email' => $this->email,
            'score' => $this->score,
            'status' => $this->status,
        ];
    }
}
```

**Key design decisions:**
- Scalar values only (int, string) — no Eloquent models, avoids SerializesModels memory leak
- Public `Channel` not `PrivateChannel` — no user/auth model exists
- `broadcastWith()` provides strict-typed payload matching the `ContactResource` shape

---

### `app/Listeners/LogContactScoreProcessed.php` (listener, event-driven)

**Analog:** `app/Infrastructure/Observers/ContactObserver.php` (single-method handler pattern, infrastructure concern)

**Imports pattern** — from analog (line 1-6):
```php
namespace App\Listeners;

use App\Events\ContactScoreProcessed;
use Illuminate\Support\Facades\Log;
```

**Core pattern** — RESEARCH.md Pattern 2 (lines 282-293):
```php
class LogContactScoreProcessed
{
    public function handle(ContactScoreProcessed $event): void
    {
        Log::channel('contact')->info('Contact score processed', [
            'id' => $event->contactId,
            'email' => $event->email,
            'score' => $event->score,
            'status' => $event->status,
        ]);
    }
}
```

**Key design decisions:**
- No `ShouldQueue` — logging is synchronous (fast path). Broadcasting happens automatically via `ShouldBroadcast` on the event.
- Laravel 13 auto-discovers this listener because `app/Listeners/` is scanned for `handle(EventType $event)` methods. No manual registration needed.
- Uses `Log::channel('contact')` — dedicated channel defined in `config/logging.php`

---

### `routes/channels.php` (route, config)

**Analog:** `routes/api.php`

**Imports pattern** (line 1-4):
```php
<?php

use Illuminate\Support\Facades\Broadcast;
```

**Pattern** — RESEARCH.md Pattern 5 (lines 368-376):
```php
<?php

use Illuminate\Support\Facades\Broadcast;

// No auth needed — using public channels (no PrivateChannel).
// All channel authorization is handled by the event's broadcastOn().
```

**Key points:**
- Minimal file — public channels don't need auth callbacks
- No `Broadcast::channel()` calls needed
- The file must exist for Laravel to load broadcast routes (even if empty)

---

### `tests/Unit/Infrastructure/Events/ContactScoreProcessedTest.php` (test, event-driven)

**Analog:** `tests/Unit/Domain/Services/ScoreCalculatorTest.php` (pure PHPUnit structure) + `tests/Feature/ScoreProcessingTest.php` (Event::fake pattern)

**No existing unit test directly uses `Event::fake()` or `Broadcast::fake()`.** This is the first test in the project to test broadcast events at the unit level. Use feature test patterns from `tests/Feature/ScoreProcessingTest.php` for the `Event::fake()` usage.

**Test class structure** — from `tests/Unit/Domain/Services/ScoreCalculatorTest.php` (lines 1-49):
```php
<?php

namespace Tests\Unit\Infrastructure\Events;

use App\Events\ContactScoreProcessed;
use PHPUnit\Framework\TestCase;
```

**Event::fake() pattern** — from `tests/Feature/ScoreProcessingTest.php` (lines 17-34) adapted to unit test:
```php
// Unit test approach: Test the event directly, not the dispatch chain.
// For dispatch assertions, use the feature test (ScoreProcessingTest).

public function test_event_creates_with_correct_data(): void
{
    $event = new ContactScoreProcessed(
        contactId: 1,
        email: 'john@example.com',
        score: 50,
        status: 'active',
    );

    $this->assertSame(1, $event->contactId);
    $this->assertSame('john@example.com', $event->email);
    $this->assertSame(50, $event->score);
    $this->assertSame('active', $event->status);
}

public function test_event_broadcasts_on_correct_channel(): void
{
    $event = new ContactScoreProcessed(
        contactId: 1,
        email: 'john@example.com',
        score: 50,
        status: 'active',
    );

    $this->assertSame('contacts.1', $event->broadcastOn()->name);
}
```

---

### `tests/Unit/Infrastructure/Listeners/LogContactScoreProcessedTest.php` (test, event-driven)

**Analog:** `tests/Feature/ContactApiTest.php` (uses Laravel TestCase for facade access, but need `Log::spy()`)

**Imports pattern** — from `tests/Feature/ContactApiTest.php` (lines 1-7) adapted:
```php
<?php

namespace Tests\Unit\Infrastructure\Listeners;

use App\Events\ContactScoreProcessed;
use App\Listeners\LogContactScoreProcessed;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
```

**Core test pattern** — RESEARCH.md Pattern (lines 524-541):
```php
// NOTE: Log::spy() requires extending Tests\TestCase (Laravel application),
// not PHPUnit\Framework\TestCase, because it uses the Laravel facade.

class LogContactScoreProcessedTest extends TestCase
{
    public function test_logs_contact_score_processed(): void
    {
        Log::spy();

        $event = new ContactScoreProcessed(
            contactId: 1,
            email: 'john@example.com',
            score: 50,
            status: 'active',
        );

        $listener = new LogContactScoreProcessed();
        $listener->handle($event);

        Log::shouldHaveReceived('channel')
            ->with('contact')
            ->once();
    }

    public function test_logs_correct_context(): void
    {
        Log::spy();

        $event = new ContactScoreProcessed(
            contactId: 1,
            email: 'john@example.com',
            score: 50,
            status: 'active',
        );

        $listener = new LogContactScoreProcessed();
        $listener->handle($event);

        Log::shouldHaveReceived('channel')
            ->with('contact')
            ->once();
    }
}
```

**Key decision:** `Log::spy()` requires Laravel's `Tests\TestCase` because it relies on the service container facade resolution.

---

### `tests/Feature/ObserverTest.php` (test, CRUD)

**Analog:** `tests/Feature/ContactApiTest.php` (exact match — same imports, same use of `RefreshDatabase`, `Contact::factory()`, `$this->assertDatabaseHas()`)

**Imports pattern** (lines 1-7):
```php
<?php

namespace Tests\Feature;

use App\Infrastructure\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
```

**Core test pattern** — from `tests/Feature/ContactApiTest.php` `test_phone_normalization_on_create()` (lines 87-100):
```php
class ObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_observer_normalizes_phone_on_saving(): void
    {
        $contact = Contact::factory()->create([
            'phone' => '(11) 99999-9999',
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'phone' => '11999999999',
        ]);
    }

    public function test_observer_does_not_modify_already_clean_phone(): void
    {
        $contact = Contact::factory()->create([
            'phone' => '11999999999',
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'phone' => '11999999999',
        ]);
    }
}
```

**Key points:**
- Uses `Contact::factory()->create()` with formatted phone number
- Assertion uses `assertDatabaseHas()` to verify the normalized value
- Tests BOTH the normalization case and the clean-phone case (prevent regression)
- Observer is already registered in `AppServiceProvider::boot()` (line 35)

---

### `src/Application/UseCases/ProcessScoreUseCase.php` (MODIFY — use-case, CRUD)

**Analog:** `src/Application/UseCases/CreateContactUseCase.php` (returns `Contact`, same namespace pattern)

**Current `execute()` signature** (line 17):
```php
public function execute(int $contactId): void
```

**Target pattern** — from `CreateContactUseCase.php` (line 17):
```php
public function execute(string $name, string $email, string $phone): Contact
```

**Modified implementation** — RESEARCH.md Pattern 3 (lines 303-324):
```php
public function execute(int $contactId): Contact
{
    $contact = $this->repository->findById($contactId);

    if ($contact === null) {
        throw new \RuntimeException("Contact not found: {$contactId}");
    }

    $contact->markAsProcessing();
    $this->repository->save($contact);

    try {
        $score = $this->calculator->calculate($contact);
        $contact->markAsActive($score);
    } catch (\Throwable) {
        $contact->markAsFailed();
    }

    $this->repository->save($contact);

    return $contact;
}
```

**Changes:**
1. Return type: `void` → `Contact`
2. Add `return $contact;` at the end

---

### `app/Jobs/ProcessContactScoreJob.php` (MODIFY — job, event-driven)

**Analog:** Its current self (lines 33-63)

**Modified `handle()` pattern** — RESEARCH.md Pattern 4 (lines 330-359):
```php
public function handle(ProcessScoreUseCase $useCase): void
{
    sleep(rand(1, 2));

    try {
        $contact = $useCase->execute($this->contactId);

        event(new ContactScoreProcessed(
            contactId: $contact->id(),
            email: $contact->email()->value,
            score: $contact->score()->value,
            status: $contact->status()->value,
        ));
    } catch (\Throwable $e) {
        try {
            $useCase->markAsFailed($this->contactId);
        } catch (\Throwable) {
        }

        if ($e instanceof \DomainException || str_contains($e->getMessage(), 'Contact not found')) {
            return;
        }

        throw $e;
    }
}
```

**Changes:**
1. Add import: `use App\Events\ContactScoreProcessed;`
2. Change `$useCase->execute($this->contactId);` to `$contact = $useCase->execute($this->contactId);`
3. Add `event(new ContactScoreProcessed(...))` block after success

---

### `config/logging.php` (MODIFY — config, config)

**Analog:** Its current self — add to `'channels'` array following existing channel patterns

**Existing single channel pattern** (lines 61-66):
```php
'single' => [
    'driver' => 'single',
    'path' => storage_path('logs/laravel.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'replace_placeholders' => true,
],
```

**Target channel** — RESEARCH.md Pattern 2 (lines 265-271):
```php
'contact' => [
    'driver' => 'single',
    'path' => storage_path('logs/contact.log'),
    'level' => 'info',
],
```

**Placement:** Add inside `'channels' => [...]` array at the end, before the closing `]`.

---

### `.env` (MODIFY — config, config)

**Analog:** Its current self

**Changes needed:**
1. Change `BROADCAST_CONNECTION=log` → `BROADCAST_CONNECTION=reverb` (line 36)
2. Add Reverb environment variables:
```
REVERB_APP_ID=contact-app
REVERB_APP_KEY=contact-app-key
REVERB_APP_SECRET=contact-app-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```

**Also update `.env.example`** with the same changes (for new devs cloning the repo).

---

### `README.md` (MODIFY — docs, docs)

**Analog:** Its current self — currently contains default Laravel README content (58 lines)

**Pattern to follow:** Replace or extend with project-specific documentation following conventions from `AGENTS.md`. The HTML/JS snippet should be added in a new "Real-time Score Updates" section.

**Target snippet** — RESEARCH.md Pattern HTML/JS (lines 588-617):
```html
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16/dist/echo.iife.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8/dist/pusher.min.js"></script>
<script>
    window.Pusher = Pusher;

    const echo = new Echo({
        broadcaster: 'reverb',
        key: 'YOUR_REVERB_APP_KEY',
        wsHost: window.location.hostname,
        wsPort: 8080,
        wssPort: 8080,
        forceTLS: false,
        enabledTransports: ['ws', 'wss'],
    });

    const contactId = 1; // Replace with actual contact ID

    echo.channel(`contacts.${contactId}`)
        .listen('ContactScoreProcessed', (e) => {
            console.log('Score updated:', e);
            document.getElementById('contact-info').innerHTML = `
                <p>ID: ${e.contact_id}</p>
                <p>Email: ${e.email}</p>
                <p>Score: ${e.score}</p>
                <p>Status: ${e.status}</p>
            `;
        });
</script>
```

---

### `tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php` (MODIFY — test, CRUD)

**Analog:** Its current self

**Changes needed — add new test method** (after line 37):
```php
public function test_execute_returns_contact(): void
{
    $contact = Contact::create(
        'John Doe',
        new Email('john@company.com'),
        new Phone('11999999999')
    );

    $repository = $this->createMock(ContactRepositoryInterface::class);
    $repository->method('findById')->with(1)->willReturn($contact);

    $calculator = $this->createMock(ScoreCalculator::class);
    $calculator->method('calculate')->with($contact)->willReturn(new Score(60));

    $useCase = new ProcessScoreUseCase($repository, $calculator);
    $result = $useCase->execute(1);

    $this->assertInstanceOf(Contact::class, $result);
    $this->assertSame(60, $result->score()->value);
}
```

Changes summarized:
1. Import `Contact` (already imported at line 6)
2. Add the new test method verifying `Contact` return type

---

### `tests/Feature/ScoreProcessingTest.php` (MODIFY — test, CRUD)

**Analog:** Its current self

**Add new test method** — RESEARCH.md Pattern (lines 483-511):
```php
use App\Events\ContactScoreProcessed;
use Illuminate\Support\Facades\Event;

public function test_score_processing_dispatches_contact_score_processed_event(): void
{
    Event::fake([ContactScoreProcessed::class]);

    $contact = Contact::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@company.com',
        'phone' => '11999999999',
        'status' => 'pending',
    ]);

    $this->postJson(self::BASE_URL . '/' . $contact->id . '/process-score');

    Event::assertDispatched(ContactScoreProcessed::class, function ($event) use ($contact) {
        return $event->contactId === $contact->id
            && $event->email === $contact->email
            && $event->score === 50
            && $event->status === 'active';
    });
}
```

**Changes:**
1. Add imports: `use App\Events\ContactScoreProcessed;` and `use Illuminate\Support\Facades\Event;`
2. Add the test method to the class

---

## Shared Patterns

### Event Dispatch from Infrastructure
**Source:** `app/Jobs/ProcessContactScoreJob.php` (Post-Modification, Pattern 4)
**Apply to:** All event dispatching code
- Events implementing `ShouldBroadcast` belong in `app/Events/` (Infrastructure), NOT in `src/Domain/Events/`
- Dispatch events from the Job (Infrastructure) after the Use Case (Application) returns, NOT inside the Use Case
- Use scalar values only in broadcast events — no Eloquent models

### PHPUnit Pure Unit Tests (No Laravel Bootstrap)
**Source:** `tests/Unit/Domain/Services/ScoreCalculatorTest.php`
```php
namespace Tests\Unit\Domain\Services;

use PHPUnit\Framework\TestCase;

class ScoreCalculatorTest extends TestCase
{
    // Uses createMock(), no Laravel facades
}
```
**Apply to:** Tests that don't need Laravel facades or service container.

### Laravel Feature Tests (With Bootstrap)
**Source:** `tests/Feature/ContactApiTest.php`
```php
namespace Tests\Feature;

use App\Infrastructure\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;
    // ...
}
```
**Apply to:** Tests needing facades (`Event::fake()`, `Log::spy()`), database, or HTTP assertions.

### Repository Pattern Binding
**Source:** `app/Providers/AppServiceProvider.php` (lines 19-22):
```php
$this->app->bind(
    ContactRepositoryInterface::class,
    EloquentContactRepository::class,
);
```
**Key pattern:** Interface-to-implementation binding in `register()`, observer registration in `boot()`. No additional bindings needed for Phase 3.

### Log Channel Configuration
**Source:** `config/logging.php` 'single' channel (lines 61-66):
```php
'single' => [
    'driver' => 'single',
    'path' => storage_path('logs/laravel.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'replace_placeholders' => true,
],
```
**Apply to:** Adding the `contact` channel — use `'driver' => 'single'` with `'level' => 'info'` and no `'replace_placeholders'`.

## No Analog Found

Files with no close match in the codebase (planner should use RESEARCH.md patterns instead):

| File | Role | Data Flow | Reason |
|------|------|-----------|--------|
| `app/Events/ContactScoreProcessed.php` | event | event-driven | No existing event classes in codebase. Use RESEARCH.md Pattern 1. |
| `app/Listeners/LogContactScoreProcessed.php` | listener | event-driven | No existing listener classes in codebase. Use RESEARCH.md Pattern 2. |

## Test Pattern Reference

| Test File | Base Class | Key Methods Used | Bootstrap Needed? |
|-----------|-----------|-----------------|-------------------|
| `tests/Unit/Infrastructure/Events/ContactScoreProcessedTest.php` | `PHPUnit\Framework\TestCase` | `$this->assertSame()` | No — pure PHPUnit |
| `tests/Unit/Infrastructure/Listeners/LogContactScoreProcessedTest.php` | `Tests\TestCase` | `Log::spy()`, `Log::shouldHaveReceived()` | Yes — needs Laravel facade |
| `tests/Feature/ObserverTest.php` | `Tests\TestCase` | `Contact::factory()`, `assertDatabaseHas()` | Yes — needs DB |
| `tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php` (MOD) | `PHPUnit\Framework\TestCase` | `createMock()`, `assertInstanceOf()` | No — pure PHPUnit |
| `tests/Feature/ScoreProcessingTest.php` (MOD) | `Tests\TestCase` | `Event::fake()`, `Event::assertDispatched()` | Yes — needs DB + HTTP |

## Metadata

**Analog search scope:** `app/`, `src/`, `tests/`, `config/`, `routes/`
**Files scanned:** 20+ files across app/, src/, tests/, config/, routes/
**Pattern extraction date:** 2026-05-18
