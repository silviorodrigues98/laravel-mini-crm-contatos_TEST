---
phase: 03-events-broadcasting-polish
reviewed: 2026-05-18T14:50:00Z
depth: standard
files_reviewed: 15
files_reviewed_list:
  - README.md
  - .env.example
  - app/Events/ContactScoreProcessed.php
  - app/Listeners/LogContactScoreProcessed.php
  - config/reverb.php
  - config/broadcasting.php
  - config/logging.php
  - routes/channels.php
  - src/Application/UseCases/ProcessScoreUseCase.php
  - app/Jobs/ProcessContactScoreJob.php
  - tests/Unit/Infrastructure/Events/ContactScoreProcessedTest.php
  - tests/Unit/Infrastructure/Listeners/LogContactScoreProcessedTest.php
  - tests/Feature/ObserverTest.php
  - tests/Feature/ScoreProcessingTest.php
  - tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php
findings:
  critical: 2
  warning: 4
  info: 2
  total: 8
status: issues_found
---

# Phase 03: Code Review Report — Events, Broadcasting & Polish

**Reviewed:** 2026-05-18T14:50:00Z
**Depth:** standard
**Files Reviewed:** 15
**Status:** issues_found

## Summary

Reviewed 15 files across events, listeners, broadcasting config, job processing, use cases, and tests. Found **2 critical issues**, **4 warnings**, and **2 info items**.

The most severe issues are: (1) the `LogContactScoreProcessed` listener is **not registered anywhere in the application** — no `EventServiceProvider`, no `->withEvents()` call in `bootstrap/app.php`, so it will never be invoked at runtime; (2) the `ProcessContactScoreJob` silently swallows a secondary failure in its fallback path, with no logging despite a comment acknowledging logging is needed.

---

## Critical Issues

### CR-01: LogContactScoreProcessed listener is never registered (orphaned listener)

**File:** `bootstrap/app.php:7-20` and `app/Listeners/LogContactScoreProcessed.php:10-18`
**Issue:** The `LogContactScoreProcessed` listener class exists and has working logic, but it is never wired to the `ContactScoreProcessed` event anywhere in the application. There is no `EventServiceProvider`, no `->withEvents()` call in `bootstrap/app.php`, and no other service provider that registers the mapping. In Laravel 11, without an explicit `->withEvents()` (which enables event discovery) or a traditional `EventServiceProvider`, the framework dispatches events but **never invokes auto-discovered listeners**. The listener will never fire at runtime.

This means the `contact.log` channel is never written to by this listener. The `ContactScoreProcessed` event still broadcasts (because `ShouldBroadcast` is handled by the framework's event dispatch core independently of registered listeners), but the logging side-effect is completely lost.

**Fix:** Register the listener. The cleanest approach for Laravel 11 is to enable event discovery in `bootstrap/app.php`:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withEvents(discover: [
        __DIR__.'/../app/Listeners',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

Alternatively, create `app/Providers/EventServiceProvider.php` with the explicit mapping:

```php
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ContactScoreProcessed::class => [
            LogContactScoreProcessed::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return true;
    }
}
```

And register it in `bootstrap/app.php`:
```php
->withProviders([
    App\Providers\EventServiceProvider::class,
])
```

### CR-02: Silent catch swallows fallback failure with no operational trace

**File:** `app/Jobs/ProcessContactScoreJob.php:52-58`
**Issue:** When `ProcessScoreUseCase::execute()` throws, the job attempts a fallback via `$useCase->markAsFailed()`. If that fallback also throws, the exception is caught by an empty `catch (\Throwable) {}` block — all error information is silently discarded. The comment on lines 56-57 explicitly states: *"Logging is needed for operational visibility."* Yet no logging occurs. This means if the secondary failure happens, the contact can remain permanently stuck in "processing" status with zero trace in any log, making debugging impossible.

**Fix:** Add logging inside the inner catch block:

```php
try {
    $useCase->markAsFailed($this->contactId);
} catch (\Throwable $inner) {
    Log::channel('contact')->error(
        'Failed to mark contact as failed after processing error',
        [
            'contact_id' => $this->contactId,
            'original_error' => $e->getMessage(),
            'fallback_error' => $inner->getMessage(),
        ]
    );
}
```

---

## Warnings

### WR-01: Weak/default Reverb credentials in .env.example

**File:** `.env.example:38-39`
**Issue:** The Reverb credentials are guessable defaults:
```
REVERB_APP_KEY=contact-app-key
REVERB_APP_SECRET=contact-app-secret
```
These values are also referenced in the README's JavaScript example (`YOUR_REVERB_APP_KEY`) which is documentation-correct, but the `.env.example` itself provides weak secrets that could easily be deployed to production unchanged. A production Reverb deployment with these credentials allows anyone to connect as an authenticated app, broadcast unauthorized events, or eavesdrop on private channels if any were used.

**Fix:** Replace with obvious placeholder values that signal "must change":

```diff
- REVERB_APP_KEY=contact-app-key
- REVERB_APP_SECRET=contact-app-secret
+ REVERB_APP_KEY=
+ REVERB_APP_SECRET=
```

Add a comment above: `# Generate with: php artisan reverb:generate --force`

### WR-02: Allowed origins wildcard in Reverb config

**File:** `config/reverb.php:85`
**Issue:** `'allowed_origins' => ['*']` permits WebSocket connections from any origin. In production, this exposes the Reverb server to cross-origin WebSocket connections from arbitrary domains.

**Fix:** Restrict to known frontend origin(s):
```php
'allowed_origins' => [
    env('REVERB_ALLOWED_ORIGINS', env('APP_URL', 'http://localhost')),
],
```

### WR-03: Duplicate test methods with misleading names in LogContactScoreProcessedTest

**File:** `tests/Unit/Infrastructure/Listeners/LogContactScoreProcessedTest.php:13-53`
**Issue:** The test class contains two methods — `test_logs_contact_score_processed` and `test_logs_correct_context` — that are functionally identical. Both create the same mock setup (`info()->once()` with no argument matching), instantiate the same event and listener, and call `handle()`. The second method's name promises it verifies that correct context data is passed to the logger, but the assertion `info()->once()` does not inspect the context array at all. This gives a false sense of coverage: the test passes even if the listener logged empty or wrong data.

**Fix:** Consolidate into one test that verifies the actual context data:

```php
public function test_logs_correct_contact_data(): void
{
    Log::shouldReceive('channel')
        ->with('contact')
        ->once()
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return $message === 'Contact score processed'
                && ($context['id'] ?? null) === 1
                && ($context['email'] ?? null) === 'john@example.com'
                && ($context['score'] ?? null) === 50
                && ($context['status'] ?? null) === 'active';
        });

    $event = new ContactScoreProcessed(
        contactId: 1,
        email: 'john@example.com',
        score: 50,
        status: 'active',
    );

    $listener = new LogContactScoreProcessed();
    $listener->handle($event);
}
```

### WR-04: Integration test does not assert `processed_at` is set

**File:** `tests/Feature/ScoreProcessingTest.php:58-62`
**Issue:** The `test_process_score_full_integration_with_sync_queue()` method asserts the final DB state has correct `status` and `score`, but does not verify `processed_at` is non-null. Since `processed_at` is a key field in the contact processing lifecycle (it should be set when status transitions to `active` or `failed`), the test should assert it was populated.

**Fix:** Add assertion for `processed_at`:

```php
$this->assertDatabaseHas('contacts', [
    'id' => $contact->id,
    'status' => 'active',
    'score' => 50,
]);

$this->assertNotNull(
    Contact::find($contact->id)->processed_at
);
```

---

## Info

### IN-01: Public channel broadcasts email address

**File:** `app/Events/ContactScoreProcessed.php:14-18` and `README.md:121-130`
**Issue:** The `ContactScoreProcessed` event uses `new Channel(...)` (a public channel) and broadcasts the contact's email address to all WebSocket subscribers who know the contact ID. While the project explicitly chose public channels (per `routes/channels.php` comment: "No auth needed -- using public channels"), broadcasting email addresses over an unauthenticated channel is a data exposure concern for a CRM system. If the requirements change to require authentication, the channel should be changed to `PrivateChannel` and an authorization route added in `routes/channels.php`.

No immediate fix required — this matches the documented design. Flagged for future consideration if access control requirements evolve.

### IN-02: Mismatch between spec (Redis queue) and .env.example (database queue)

**File:** `.env.example:44`
**Issue:** The `.env.example` sets `QUEUE_CONNECTION=database` while the project spec and README both reference Redis as the production queue backend. New developers who simply copy `.env.example` will get database-backed queues, not Redis. They would need to manually switch to `QUEUE_CONNECTION=redis` and ensure Redis is configured. The `.env.example` should steer developers toward the intended production stack.

**Fix:** Either change the default to `redis` or add a commented Redis queue config with a hint:
```
# For Redis queue (production), uncomment:
# QUEUE_CONNECTION=redis
# REDIS_QUEUE_CONNECTION=default
```

---

_Reviewed: 2026-05-18T14:50:00Z_
_Reviewer: gsd-code-reviewer (adversarial)_
_Depth: standard_
