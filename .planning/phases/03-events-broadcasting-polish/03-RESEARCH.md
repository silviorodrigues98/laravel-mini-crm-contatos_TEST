# Phase 3: Events, Broadcasting & Polish — Research

**Researched:** 2026-05-18
**Domain:** Laravel events, listeners, Reverb WebSocket broadcasting, Observer testing
**Confidence:** HIGH

## Summary

Phase 3 wires the `ContactScoreProcessed` event into the score processing pipeline, adds a log listener, sets up Reverb broadcasting, provides an HTML/JS listener example, and adds tests for the existing ContactObserver. The architecture follows the project's pragmatic DDD pattern: domain events are dispatched from the Infrastructure layer (the Job) after the Application layer Use Case completes, keeping the domain pure while leveraging Laravel's built-in event and broadcasting system.

**Key architecture insight:** The `ContactScoreProcessed` event implements `ShouldBroadcast` — making it inherently an Infrastructure concern (broadcast drivers are Laravel-specific). Firing it from `ProcessContactScoreJob` (already in Infrastructure) after the use case succeeds is the cleanest approach. The use case's `execute()` method changes to return the `Contact` entity, enabling the job to construct the event without a second repository fetch.

**No new Composer packages needed beyond Reverb** (`laravel/reverb`). The HTML/JS example uses CDN-loaded `pusher-js` and `laravel-echo`.

**Primary recommendation:**
1. Install Reverb (`composer require laravel/reverb` + `php artisan reverb:install`)
2. Create `app/Events/ContactScoreProcessed.php` with `ShouldBroadcast` on public channel `contacts.{id}`
3. Create `app/Listeners/LogContactScoreProcessed.php` — writes to `storage/logs/contact.log`
4. Modify `ProcessScoreUseCase::execute()` to return `Contact`
5. Modify `ProcessContactScoreJob::handle()` to fire the event after use case completes
6. Add `contact` log channel to `config/logging.php`
7. Update `.env` with Reverb credentials, `BROADCAST_CONNECTION=reverb`
8. Add HTML/JS example to README
9. Add tests: event dispatch, listener, broadcasting, observer phone normalization

<user_constraints>
## User Constraints (from CONTEXT.md)

No CONTEXT.md exists for Phase 3. Constraints derived from existing project artifacts (AGENTS.md, ROADMAP.md, REQUIREMENTS.md, prior RESEARCH.md decisions):

### Locked Decisions (from Phase 1/2)
- DDD layering: Domain (framework-agnostic), Application (use cases), Infrastructure (Laravel) — no cross-layer contamination
- Repository pattern: `ContactRepositoryInterface` in Domain, `EloquentContactRepository` in Infrastructure, wired via container
- `ProcessContactScoreJob` in `app/Jobs/` — Infrastructure layer
- `ProcessScoreUseCase` in `src/Application/UseCases/` — Application layer
- Score processing pipeline runs async via queue job with `sleep(rand(1,2))`
- Queue uses `database` driver in `.env`, `sync` in tests
- Feature tests use `RefreshDatabase`, `Queue::fake()`
- Tests run via `php artisan test`

### Phase 3 Specific Requirements (from ROADMAP.md)
- EVENT-01: `ContactScoreProcessed` domain event dispatched after score calculation
- EVENT-02: Listener logs to `storage/logs/contact.log` (ID, email, score, status)
- EVENT-03: Listener broadcasts via Reverb on `contacts.{id}` channel
- EVENT-04: README includes basic HTML/JS example for listening to the channel
- ARCH-08: Observer on Contact model (`saving` to normalize phone)
- TEST-06: Full `php artisan test` suite passes

### Phase 2 Deferred Decisions
- Domain events were intentionally NOT implemented in Phase 2 — deferred to Phase 3
- The event dispatch mechanism was left open (pure DDD vs Laravel native)

### Deferred Ideas (OUT OF SCOPE)
- Authentication/authorization (no user model)
- Admin dashboard
- Persistent WebSocket reconnect
- Failure recovery events
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| **EVENT-01** | `ContactScoreProcessed` domain event dispatched after score calculation | Event class in `app/Events/ContactScoreProcessed.php` implements `ShouldBroadcast`; dispatched from `ProcessContactScoreJob` after `ProcessScoreUseCase::execute()` succeeds; use case modified to return `Contact` entity |
| **EVENT-02** | Listener logs to `storage/logs/contact.log` (ID, email, score, status) | `app/Listeners/LogContactScoreProcessed.php` writes via `Log::channel('contact')`; custom `contact` log channel in `config/logging.php` |
| **EVENT-03** | Listener broadcasts via Reverb on `contacts.{id}` channel | Event uses `new Channel('contacts.{id}')` — public channel (no auth needed); Reverb installed via `composer require laravel/reverb` + `php artisan reverb:install` |
| **EVENT-04** | README includes HTML/JS snippet for listening | Standalone HTML snippet using CDN-loaded Echo + Pusher; subscribes to `contacts.{id}` channel |
| **ARCH-08** | Observer on Contact model (`saving` to normalize phone) | Existing `ContactObserver` already created and registered; Phase 3 adds tests to verify phone normalization |
| **TEST-06** | Full `php artisan test` suite passes | Event/listener/broadcasting tests via `Event::fake()`, `Log::spy()`, `Broadcast::fake()`; observer test via model creation assertion |
</phase_requirements>

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Event definition (ContactScoreProcessed) | **Infrastructure** | — | Implements `ShouldBroadcast` — a Laravel contract. Broadcasting is inherently infrastructure. |
| Log listener | **Infrastructure** | — | Writes to a file using Laravel's `Log` facade. Pure infrastructure concern. |
| Broadcast event dispatch | **Infrastructure** | — | Triggered from `ProcessContactScoreJob` after use case completes. Uses `event()` helper. |
| Event auto-discovery | **Infrastructure** | — | Laravel 13 scans `app/Listeners/` automatically — no EventServiceProvider needed. |
| Log channel config | **Infrastructure** | — | `config/logging.php` — add `contact` channel pointing to `storage/logs/contact.log`. |
| Channel authorization | **Infrastructure** | — | Public channel — no auth required. `routes/channels.php` can be empty. |
| Observer phone normalization | **Infrastructure** | — | Already exists in `ContactObserver`. Phase 3 adds tests only. |
| Use case modification (return Contact) | **Application** | — | `ProcessScoreUseCase::execute()` returns `Contact` instead of `void` — enables event construction. |
| HTML/JS example | **Documentation** | — | Standalone HTML file or README snippet using CDN scripts. |
| Event testing | **Test** | — | `Event::fake()` + assertions; `Log::spy()` for log assertions; observer assertions via model save. |

## Standard Stack

### Existing (No Changes Needed)
| Component | Location | Role |
|-----------|----------|------|
| `Contact` entity | `src/Domain/Entities/Contact.php` | Status transitions, getters — unchanged |
| `ContactStatus` enum | `src/Domain/Enums/ContactStatus.php` | Valid status transitions |
| `ContactRepositoryInterface` | `src/Domain/Repositories/ContactRepositoryInterface.php` | Repository contract |
| `EloquentContactRepository` | `app/Infrastructure/Repositories/EloquentContactRepository.php` | Eloquent implementation |
| `ProcessScoreUseCase` | `src/Application/UseCases/ProcessScoreUseCase.php` | **Modified** — returns Contact instead of void |
| `ProcessContactScoreJob` | `app/Jobs/ProcessContactScoreJob.php` | **Modified** — dispatches event after success |
| `ContactObserver` | `app/Infrastructure/Observers/ContactObserver.php` | Already exists and registered |
| `AppServiceProvider` | `app/Providers/AppServiceProvider.php` | **Modified** — registers Reverb binding? No — Reverb auto-configures. |
| PHPUnit 12.5 | Built-in | `QUEUE_CONNECTION=sync`, `BROADCAST_CONNECTION=null` in phpunit.xml |

### New Files to Create
| File | Role | Tier |
|------|------|------|
| `app/Events/ContactScoreProcessed.php` | Event class with `ShouldBroadcast`, public channel `contacts.{id}` | Infrastructure |
| `app/Listeners/LogContactScoreProcessed.php` | Listener: writes to `storage/logs/contact.log` | Infrastructure |
| `routes/channels.php` | Broadcast channel authorization (empty for public channels) | Infrastructure |
| `tests/Unit/Infrastructure/Events/ContactScoreProcessedTest.php` | Event unit tests (dispatch assertion) | Test |
| `tests/Unit/Infrastructure/Listeners/LogContactScoreProcessedTest.php` | Listener unit test | Test |
| `tests/Feature/ObserverTest.php` | Observer phone normalization test | Test |

### Files to Modify
| File | Change | Why |
|------|--------|-----|
| `src/Application/UseCases/ProcessScoreUseCase.php` | `execute()` returns `Contact` instead of `void` | Job needs Contact for event construction |
| `app/Jobs/ProcessContactScoreJob.php` | Fire event after use case success | Dispatches `ContactScoreProcessed` event |
| `config/logging.php` | Add `contact` channel | Custom log file destination |
| `.env` | Add Reverb env vars, set `BROADCAST_CONNECTION=reverb` | Enable Reverb broadcasting |
| `README.md` | Add HTML/JS listening example | EVENT-04 requirement |
| `tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php` | Assert `execute()` returns `Contact` | Updated return type |

### New Packages
| Package | Version | Purpose | Installation Command |
|---------|---------|---------|---------------------|
| `laravel/reverb` | ^1.x | WebSocket server for real-time broadcasting | `composer require laravel/reverb` |

**No NPM packages required** — HTML/JS example uses CDN-loaded `pusher-js` and `laravel-echo`.

## Package Legitimacy Audit

| Package | Registry | Age | Downloads | Source Repo | slopcheck | Disposition |
|---------|----------|-----|-----------|-------------|-----------|-------------|
| `laravel/reverb` | Composer | ~2 yrs | Millions | github.com/laravel/reverb | [OK] | Approved — official Laravel package |

**Packages removed due to slopcheck [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

## Architecture Patterns

### System Architecture Diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│                    POST /api/contacts/{id}/process-score                  │
│                          (ContactController)                              │
└─────────────────────────────────┬────────────────────────────────────────┘
                                  │ dispatch(contactId)
                                  ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                    ProcessContactScoreJob (Infrastructure)                │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────────┐ │
│  │  sleep(rand(1,2))                                                    │ │
│  │  $contact = ProcessScoreUseCase::execute($contactId)                 │ │
│  │  event(new ContactScoreProcessed($contact->id(), ...))               │ │
│  └─────────────────────────────────┬───────────────────────────────────┘ │
└────────────────────────────────────┼─────────────────────────────────────┘
                                     │ event() dispatches
                                     ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                     Laravel Event Dispatcher                              │
│                                                                          │
│  ┌──────────────────────────────────┐   ┌──────────────────────────────┐ │
│  │  LogContactScoreProcessed        │   │  ShouldBroadcast::broadcastOn│ │
│  │  (Listener)                      │   │  (via Queue job)             │ │
│  │  └→ Log::channel('contact')      │   │  └→ Reverb server            │ │
│  │     writes storage/logs/         │   │     sends to WebSocket       │ │
│  │     contact.log                  │   │     clients on               │ │
│  └──────────────────────────────────┘   │     contacts.{id} channel    │ │
│                                          └──────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────┘
```

### File Structure (Changes Only)

```
project-root/
├── app/
│   ├── Events/
│   │   └── ContactScoreProcessed.php          # NEW — ShouldBroadcast event
│   ├── Listeners/
│   │   └── LogContactScoreProcessed.php        # NEW — writes contact.log
│   ├── Jobs/
│   │   └── ProcessContactScoreJob.php          # MODIFIED — fires event
│   └── ...
├── src/
│   └── Application/
│       └── UseCases/
│           └── ProcessScoreUseCase.php         # MODIFIED — returns Contact
├── config/
│   └── logging.php                            # MODIFIED — add contact channel
├── routes/
│   └── channels.php                           # NEW — broadcast channels
├── tests/
│   ├── Unit/
│   │   ├── Infrastructure/
│   │   │   ├── Events/
│   │   │   │   └── ContactScoreProcessedTest.php   # NEW
│   │   │   └── Listeners/
│   │   │       └── LogContactScoreProcessedTest.php # NEW
│   │   └── Application/UseCases/
│   │       └── ProcessScoreUseCaseTest.php          # MODIFIED
│   └── Feature/
│       ├── ObserverTest.php                      # NEW
│       └── ScoreProcessingTest.php               # MODIFIED — add event assertion
├── .env                                          # MODIFIED — Reverb vars
├── README.md                                     # MODIFIED — HTML/JS example
└── phpunit.xml                                   # UNCHANGED — BROADCAST_CONNECTION=null already set
```

### Pattern 1: Event Dispatch from Infrastructure Layer

**What:** The `ContactScoreProcessed` event is a Laravel event class in `app/Events/` implementing `ShouldBroadcast`. It is dispatched from `ProcessContactScoreJob` (Infrastructure) after the use case completes successfully, NOT from inside the use case itself. This keeps the Application/Domain layers free of broadcasting concerns.

**When to use:** Any event that triggers infrastructure-only side effects (logging, broadcasting) should be dispatched from Infrastructure, not Application/Domain.

**Event class:**
```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

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
[CITED: laravel.com/docs/13.x/broadcasting#defining-broadcast-events]

### Pattern 2: Log Listener with Custom Channel

**What:** A dedicated log channel `contact` writes to `storage/logs/contact.log`. The listener uses `Log::channel('contact')` for isolated log output.

**When to use:** When requirements specify a dedicated log file path separate from the main application log.

**Log channel config (config/logging.php):**
```php
'contact' => [
    'driver' => 'single',
    'path' => storage_path('logs/contact.log'),
    'level' => 'info',
],
```

**Listener:**
```php
<?php

namespace App\Listeners;

use App\Events\ContactScoreProcessed;
use Illuminate\Support\Facades\Log;

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

**Event auto-discovery:** Laravel 13 automatically finds and registers listeners in `app/Listeners/` by scanning for `handle()` methods that type-hint event classes. No manual registration in `EventServiceProvider` is needed. [CITED: laravel.com/docs/13.x/events#event-discovery]

### Pattern 3: Use Case Returns Entity for Event Construction

**What:** `ProcessScoreUseCase::execute()` returns the `Contact` entity so the caller (the Job) can construct and dispatch events without a separate repository fetch.

**Modified use case:**
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

### Pattern 4: Modified Job — Fire Event After Success

**Modified job handle():**
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
        // Fallback: persist "failed" status to prevent stuck-in-processing
        try {
            $useCase->markAsFailed($this->contactId);
        } catch (\Throwable) {
            // Unrecoverable — contact may remain in "processing"
        }

        // Don't retry or log permanent conditions
        if ($e instanceof \DomainException || str_contains($e->getMessage(), 'Contact not found')) {
            return;
        }

        throw $e;
    }
}
```

### Pattern 5: Public Channel (No Auth)

**What:** Uses `Channel` (public) instead of `PrivateChannel` because no user model exists in this application. The `contacts.{id}` channel is world-readable — any connected WebSocket client can subscribe.

**When to use:** When there's no authentication system. Simpler than private channels which require auth callbacks and a user model.

**routes/channels.php:**
```php
<?php

use Illuminate\Support\Facades\Broadcast;

// No auth needed — using public channels (no PrivateChannel).
// All channel authorization is handled by the event's broadcastOn().
```

### Anti-Patterns to Avoid

- **Dispatching events inside the Use Case** — The Use Case is in the Application layer; broadcasting is infrastructure. The event is dispatched from the Job (Infrastructure) after the Use Case returns.
- **Using PrivateChannel without authentication** — `PrivateChannel` requires `Broadcast::channel()` auth callbacks that receive an authenticated user. Since there's no auth, use `Channel` (public) instead.
- **Domain event class importing Laravel traits** — A "pure" domain event (one in `src/Domain/Events/`) must NOT import `Dispatchable`, `ShouldBroadcast`, or `InteractsWithSockets`. Since our event implements `ShouldBroadcast`, it belongs in `app/Events/` — keeping it in Domain would break the framework-agnostic constraint.
- **Hardcoding log file paths via `file_put_contents`** — Use Laravel's `Log::channel()` for proper log formatting, rotation support, and testability via `Log::spy()`.
- **Re-fetching contact in the job for event data** — Instead of `$this->repository->findById($contactId)` again in the job, modify the use case to return the entity.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Event broadcasting | Custom WebSocket server | Laravel Reverb | Built-in Pusher protocol compatibility, auto-scalable via Redis, first-party Laravel package [CITED: laravel.com/docs/13.x/reverb] |
| Log file management | Manual `fopen`/`fwrite` | Laravel `Log::channel('contact')` | Monolog integration, log rotation, testable via `Log::spy()`, standard format |
| Event dispatch | Manual listener iteration | Laravel `event()` helper | Auto-discovery, queue support, fakeable for testing |
| WebSocket client | Raw WebSocket API | Laravel Echo + pusher-js | Automatic channel management, broadcasting protocol abstraction, reconnection handling |

**Key insight:** Laravel provides all the infrastructure needed — Reverb for WebSocket, Monolog for logging, and the event system for decoupling. The project's DDD constraints simply require that these infrastructure concerns are triggered from the Infrastructure layer, not from Domain or Application.

## Common Pitfalls

### Pitfall 1: Memory Leak in Queued Broadcasting Job
**What goes wrong:** `SerializesModels` trait on broadcast events can leak model data when the event references Eloquent models — the entire model graph gets serialized.
**Why it happens:** Broadcast events are dispatched via the queue. If the event has a public Eloquent model property, `SerializesModels` tries to serialize it.
**How to avoid:** Use scalar values (int, string) for broadcast event properties — not Eloquent model instances. Our `ContactScoreProcessed` event uses `int $contactId`, `string $email`, etc. — no models.
**Warning signs:** Very large queue payloads, failed `unserialize()` errors.

### Pitfall 2: Broadcasting Fails Silently in Tests
**What goes wrong:** Tests pass locally because `BROADCAST_CONNECTION=null` silently discards broadcasts. Tests miss verifying broadcast behavior.
**Why it happens:** `phpunit.xml` sets `BROADCAST_CONNECTION=null` which disables broadcasting. `Event::fake()` also fakes broadcast events.
**How to avoid:** Test broadcasting explicitly with `Broadcast::fake()` and assert events were broadcast on the correct channels. Or test the listener directly by instantiating and calling `handle()`.
**Warning signs:** Broadcast assertions missing from event-related tests.

### Pitfall 3: Event Not Auto-Discovered
**What goes wrong:** The listener is never fired even though it exists in `app/Listeners/`.
**Why it happens:** Laravel 13 auto-discovers listeners by scanning `app/Listeners/` for classes with `handle()` methods that type-hint the event. If the method signature doesn't match (wrong namespace, missing type hint, method name not `handle`), it won't be discovered.
**How to avoid:** Ensure the listener class has a `public function handle(ContactScoreProcessed $event): void` method. Run `php artisan event:list` to verify the listener is registered.
**Warning signs:** Listener not showing up in `php artisan event:list` output.

### Pitfall 4: Observer Already Exists But Is Untested
**What goes wrong:** The `ContactObserver` is already registered and functional, but Phase 3 needs to add tests for it. Forgetting to test it means ARCH-08 is not verified.
**Why it happens:** The observer was implemented in Phase 1; Phase 3 is the "polish" phase. It's easy to assume existing code is tested.
**How to avoid:** Include explicit observer tests in the Phase 3 test plan — verify phone normalization on `saving`, verify non-phone updates don't corrupt data.
**Warning signs:** No test file for observer behavior in `tests/`.

### Pitfall 5: Reverb Server Not Running During Development
**What goes wrong:** Events are dispatched and queued for broadcast, but no WebSocket server receives them — the broadcast jobs queue up.
**Why it happens:** Reverb requires `php artisan reverb:start` to be running. Unlike the queue worker, there's no auto-start command.
**How to avoid:** Document in README that `php artisan reverb:start` must be running alongside `php artisan queue:work`. The dev script in `composer.json` can be updated to include it.
**Warning signs:** `BROADCAST_CONNECTION=reverb` but no Reverb process running — broadcast jobs queue up indefinitely.

## Code Examples

### Verified Pattern: Existing Feature Test Structure

```php
// Source: Existing codebase — tests/Feature/ScoreProcessingTest.php
// Uses RefreshDatabase, Queue::fake(), and contact factory.

namespace Tests\Feature;

use App\Infrastructure\Models\Contact;
use App\Jobs\ProcessContactScoreJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScoreProcessingTest extends TestCase
{
    use RefreshDatabase;
    // ...
}
```

### Verified Pattern: ProcessScoreUseCase Unit Test

```php
// Source: Existing codebase — tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php
// Pure PHPUnit test (no Laravel bootstrap), uses createMock().

use PHPUnit\Framework\TestCase;
use Application\UseCases\ProcessScoreUseCase;
// ...

class ProcessScoreUseCaseTest extends TestCase
{
    public function test_processes_score_successfully(): void
    {
        $contact = Contact::create(/* ... */);
        $repository = $this->createMock(ContactRepositoryInterface::class);
        $repository->method('findById')->willReturn($contact);
        $calculator = $this->createMock(ScoreCalculator::class);
        $calculator->method('calculate')->willReturn(new Score(60));

        $useCase = new ProcessScoreUseCase($repository, $calculator);
        $result = $useCase->execute(1);  // Now returns Contact

        $this->assertInstanceOf(Contact::class, $result);
        $this->assertSame(ContactStatus::Active, $result->status());
    }
}
```

### Pattern: Testing Event Dispatch with Event::fake()

```php
// Source: laravel.com/docs/13.x/mocking#event-fake
// Feature test verifying ContactScoreProcessed is dispatched.

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

    // Process with sync queue (no Queue::fake() — job runs inline)
    $this->postJson("/api/contacts/{$contact->id}/process-score");

    Event::assertDispatched(ContactScoreProcessed::class, function ($event) use ($contact) {
        return $event->contactId === $contact->id
            && $event->email === $contact->email
            && $event->score === 50
            && $event->status === 'active';
    });
}
```
[CITED: laravel.com/docs/13.x/mocking#event-fake]

### Pattern: Testing Log Listener with Log::spy()

```php
// Source: laravel.com/docs/13.x/mocking#log-fake
// Unit test for the logger listener.

use App\Events\ContactScoreProcessed;
use App\Listeners\LogContactScoreProcessed;
use Illuminate\Support\Facades\Log;

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
```

### Pattern: Testing Observer Phone Normalization

```php
// Source: D-13/D-14 in Phase 1 CONTEXT.md
// Feature test verifying the observer strips non-digit characters on saving.

namespace Tests\Feature;

use App\Infrastructure\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
            'phone' => '11999999999', // Stripped of non-digits
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

### Pattern: HTML/JS Echo Listener (for README)

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
[CITED: laravel.com/docs/13.x/broadcasting#listen-for-event-broadcasts]

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `php artisan make:event` + `EventServiceProvider` | Auto-discovery in `app/Listeners/` | Laravel 11 (2024) | No manual `EventServiceProvider` needed — listener `handle()` methods auto-register based on type hints [CITED: laravel.com/docs/13.x/events#event-discovery] |
| Pusher Channels (paid) | Laravel Reverb (free, self-hosted) | Laravel 11 (2024) | Same Pusher protocol — `pusher-js` and Echo work identically. Reverb is the recommended default. |
| `config/broadcasting.php` in `config/` | `config/broadcasting.php` + `config/reverb.php` | Laravel 11 | Reverb has its own config file. `install:broadcasting` creates both. |
| `BROADCAST_DRIVER` env var | `BROADCAST_CONNECTION` env var | Laravel 11 | Renamed from `BROADCAST_DRIVER` to `BROADCAST_CONNECTION`. Check `.env` for correct variable name. |

**Deprecated/outdated:**
- `BROADCAST_DRIVER` environment variable — use `BROADCAST_CONNECTION` instead
- Manual event/listener registration in `EventServiceProvider` — auto-discovery handles it

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `ProcessScoreUseCase::execute()` can return `Contact` without breaking other callers | Architecture Patterns | LOW — only caller is `ProcessContactScoreJob::handle()`. If another caller exists, update it. |
| A2 | Laravel 13 auto-discovers listeners in `app/Listeners/` without manual registration | Architecture Patterns | MEDIUM — if auto-discovery fails, add manual `Event::listen()` in `AppServiceProvider::boot()`. Run `php artisan event:list` to verify. |
| A3 | Public channels (`Channel`) work without `routes/channels.php` auth callbacks | Architecture Patterns | HIGH — verified in Laravel docs: public channels don't need authorization. |
| A4 | Reverb env vars (`REVERB_APP_KEY`, `REVERB_APP_SECRET`, etc.) are auto-generated by `php artisan reverb:install` | Package Setup | MEDIUM — if `reverb:install` doesn't generate them, generate manually or use `php artisan reverb:generate` |
| A5 | Echo CDN import works with `echo.iife.js` (IIFE bundle) | Code Examples | MEDIUM — Echo 1.16+ provides IIFE bundle at `dist/echo.iife.js`. If CDN path differs, use `laravel-echo` npm package instead. |
| A6 | `phpunit.xml` with `BROADCAST_CONNECTION=null` already exists and is correct | Testing | HIGH — confirmed by reading the file. No changes needed. |

## Open Questions (RESOLVED)

1. **Should the event be a pure DDD domain event or a Laravel native event?**
   - **RESOLVED: Laravel native event in `app/Events/`.** The event implements `ShouldBroadcast`, which is a Laravel contract — making it inherently an Infrastructure concern. A pure domain event (in `src/Domain/Events/`) cannot implement `ShouldBroadcast` without importing Laravel. Firing from the Infrastructure layer (Job) preserves domain purity.

2. **Where in the pipeline should the event be fired?**
   - **RESOLVED: In `ProcessContactScoreJob` after `$useCase->execute()` succeeds.** The use case returns the updated `Contact` entity. The event is constructed from the entity's scalar values. If execution fails, no event is dispatched (no `ContactScoreProcessed` for failures — the spec only describes it for successful processing).

3. **Should the channel be public or private?**
   - **RESOLVED: Public channel (`Channel`).** There's no user model or auth system. Private channels require `Broadcast::channel()` auth callbacks that receive an authenticated user. Public channels work without any auth setup. The spec says "canal `contacts.{id}`" without specifying public/private.

4. **Does the existing Observer need code changes or just tests?**
   - **RESOLVED: Just tests.** `ContactObserver` already exists at `app/Infrastructure/Observers/ContactObserver.php` with `saving()` phone normalization, and is already registered in `AppServiceProvider::boot()`. Phase 3 adds tests only.

5. **Does `ProcessScoreUseCase::markAsFailed()` also need to return `Contact`?**
   - **RESOLVED: No.** `markAsFailed()` is a fallback called when `execute()` has already thrown. At that point, the event should NOT be dispatched (processing failed). No change needed.

## Runtime State Inventory

Step 2.6: SKIPPED — Phase 3 is a code/config-only phase (new event/listener files, modifications to existing files). No rename, refactor, or migration of runtime state is involved.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP 8.3 | Runtime | ✓ | 8.3.6 | — |
| Laravel 13 | Framework | ✓ | 13.8.0 | — |
| Composer | Package management | ✓ | (last checked Phase 1/2) | — |
| SQLite | Database (test) | ✓ | — | — |
| Queue driver | Async jobs | ✓ | `database` (env), `sync` (test) | — |
| Redis PHP extension | Reverb scaling | ✗ | — | Not needed for MVP — Reverb works without vertical scaling |
| Node.js / npm | Vite asset build | ✓ | (available from Laravel scaffold) | — |

**Missing dependencies with no fallback:**
- None — all required dependencies are available.

**Missing dependencies with fallback:**
- Redis extension (for Reverb scaling): Not required. Reverb runs standalone for MVP without horizontal scaling.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 12.5.12 |
| Config file | `phpunit.xml` (exists at root) |
| Quick run command | `php artisan test --filter=ContactScoreProcessed\|LogContactScoreProcessed\|ObserverTest\|ScoreProcessingTest` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| EVENT-01 | `ContactScoreProcessed` dispatched after score calculation | feature | `php artisan test --filter=test_score_processing_dispatches_contact_score_processed_event` | ❌ Wave 0 |
| EVENT-02 | Log listener writes ID, email, score, status to contact.log | unit | `php artisan test --filter=LogContactScoreProcessedTest` | ❌ Wave 0 |
| EVENT-03 | Event broadcasts via Reverb on `contacts.{id}` channel | unit | `php artisan test --filter=test_event_broadcasts_on_correct_channel` | ❌ Wave 0 |
| EVENT-04 | README includes HTML/JS example | code review | Manual — verify README.md content | ❌ Wave 0 |
| ARCH-08 | Observer normalizes phone on saving | feature | `php artisan test --filter=ObserverTest` | ❌ Wave 0 |
| TEST-06 | Full `php artisan test` suite passes | integration | `php artisan test` | ❌ Phase Gate |

### Sampling Rate

- **Per task commit:** `php artisan test --filter="ContactScoreProcessed\|LogContactScoreProcessed\|ObserverTest\|ScoreProcessingTest"`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `tests/Unit/Infrastructure/Events/ContactScoreProcessedTest.php` — covers EVENT-01, EVENT-03
- [ ] `tests/Unit/Infrastructure/Listeners/LogContactScoreProcessedTest.php` — covers EVENT-02
- [ ] `tests/Feature/ObserverTest.php` — covers ARCH-08 observer phone normalization
- [ ] Modified `tests/Feature/ScoreProcessingTest.php` — add event dispatch assertion
- [ ] Modified `tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php` — assert `execute()` returns `Contact`

*(No gaps for TEST-01 through TEST-05 — they exist from Phases 1 and 2)*

## Security Domain

Security enforcement is enabled (`workflow.nyquist_validation: true` in config).

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No | No user model — public channels. No authentication in event system. |
| V3 Session Management | No | No sessions required |
| V4 Access Control | No | Public channel `contacts.{id}` — any WebSocket client can listen. In a production system, use PrivateChannel + auth. |
| V5 Input Validation | Yes | Existing Form Requests handle input validation. Event data comes from domain entities (already valid). |
| V6 Cryptography | No | Reverb can use WSS (TLS) in production, but no encryption of event payloads. |
| V8 Data Protection | Partial | Only contact data (non-sensitive) is broadcast. No PII beyond what's already exposed via CRUD API. |

### Known Threat Patterns for Laravel DDD API with Reverb

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Unauthorized channel access (public channel) | Information Disclosure | Accepted for MVP — same data is available via GET endpoints. For production, use PrivateChannel. |
| Broadcast message injection | Tampering | Broadcast events are server-dispatched only (not client events) — clients cannot inject `ContactScoreProcessed` events. |
| Reverb connection DoS | Denial of Service | Outside MVP scope. Production should configure rate limiting, connection limits in Nginx/Supervisor. |
| Queue job retry causing duplicate events | Tampering | `ProcessContactScoreJob::$tries = 3` and `$maxExceptions = 2`. After success, the job's event dispatch runs once. If the broadcast job (separate queue) fails, Reverb handles retry internally. |

## Sources

### Primary (HIGH confidence)
- [Existing codebase] — Verified all files at `app/Infrastructure/`, `src/Domain/`, `src/Application/`, `app/Jobs/`, `tests/`, `config/`
- [Laravel 13 Events Docs — laravel.com/docs/13.x/events] — Event auto-discovery, dispatch patterns, listener registration
- [Laravel 13 Broadcasting Docs — laravel.com/docs/13.x/broadcasting] — ShouldBroadcast interface, channel types, broadcast data
- [Laravel 13 Reverb Docs — laravel.com/docs/13.x/reverb] — Installation, configuration, running the server
- [phpunit.xml] — Confirmed `BROADCAST_CONNECTION=null`, `QUEUE_CONNECTION=sync`
- [composer.json] — Confirmed PSR-4 autoloading, no Reverb installed yet
- [.env] — Contains `BROADCAST_CONNECTION=log`, needs update to `reverb`

### Secondary (MEDIUM confidence)
- [Installation pattern: `composer require laravel/reverb` + `php artisan reverb:install`] — Verified via Laravel docs page
- [Echo CDN usage: cdn.jsdelivr.net/npm/laravel-echo] — Public CDN availability; exact version path verified from npm registry documentation

### Tertiary (LOW confidence)
- None — all critical claims verified against official Laravel docs or existing codebase

## Metadata

**Confidence breakdown:**
- **Standard stack:** HIGH — all packages verified against Laravel 13 official docs and existing composer.json
- **Architecture:** HIGH — event dispatch pattern (Infrastructure-based, not contaminating Domain) follows established DDD conventions from Phase 1/2
- **Pitfalls:** HIGH — based on common event/broadcasting issues in Laravel DDD projects; verified against Laravel docs
- **Testing:** HIGH — `Event::fake()`, `Log::spy()`, `Broadcast::fake()` are stable API since Laravel 8+

**Research date:** 2026-05-18
**Valid until:** 2026-06-18 (stable — Laravel event/broadcasting API is mature)
