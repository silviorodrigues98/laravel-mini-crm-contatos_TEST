---
phase: 02-score-processing
reviewed: 2026-05-18T12:00:00Z
depth: standard
files_reviewed: 16
files_reviewed_list:
  - src/Domain/Services/Scoring/ScoreScoringStrategy.php
  - src/Domain/Services/Scoring/EmailDomainScoringStrategy.php
  - src/Domain/Services/Scoring/NameLengthScoringStrategy.php
  - src/Domain/Services/Scoring/PhoneDddScoringStrategy.php
  - src/Domain/Services/ScoreCalculator.php
  - src/Application/UseCases/ProcessScoreUseCase.php
  - app/Jobs/ProcessContactScoreJob.php
  - app/Http/Controllers/Api/ContactController.php
  - routes/api.php
  - app/Providers/AppServiceProvider.php
  - tests/Unit/Domain/Services/Scoring/EmailDomainScoringStrategyTest.php
  - tests/Unit/Domain/Services/Scoring/NameLengthScoringStrategyTest.php
  - tests/Unit/Domain/Services/Scoring/PhoneDddScoringStrategyTest.php
  - tests/Unit/Domain/Services/ScoreCalculatorTest.php
  - tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php
  - tests/Feature/ScoreProcessingTest.php
findings:
  critical: 2
  warning: 4
  info: 2
  total: 8
status: issues_found
---

# Phase 02: Code Review Report — Score Processing

**Reviewed:** 2026-05-18T12:00:00Z
**Depth:** standard
**Files Reviewed:** 16
**Status:** issues_found

## Summary

Sixteen files implementing score processing, scoring strategies, queue jobs, and controllers were reviewed at standard depth. Two critical bugs were found: (1) a type mismatch in `UpdateContactUseCase` that crashes on partial updates, and (2) a data inconsistency path where contact processing failure leaves the record permanently stuck in "processing" state. Four warnings highlight layered architecture violations, fragile design by coincidence, a subdomain-bypass edge case in the email scoring strategy, and no retry tolerance for transient failures.

---

## Critical Issues

### CR-01: Null passed to string-typed parameters crashes partial contact updates

**File:** `src/Application/UseCases/UpdateContactUseCase.php:17`
**Issue:** The `execute(int $id, string $name, string $email, string $phone): ?Contact` method declares all three data parameters as `string` (non-nullable), but the controller at `app/Http/Controllers/Api/ContactController.php:87-91` passes them as `$data['name'] ?? null`, `$data['email'] ?? null`, `$data['phone'] ?? null`.

The `UpdateContactRequest` (lines 17-21) uses `'sometimes'` validation rules, meaning partial updates are valid — only the fields present in the request body are returned by `$request->validated()`. When a field is absent, `$data['key'] ?? null` evaluates to `null`, and PHP 8+ throws a `TypeError` when `null` is passed to a `string`-typed parameter, regardless of `declare(strict_types)`.

The method body already handles null defensively (`if ($name !== null)`), which confirms the developer **intended** null to be accepted, but the type signature contradicts this intent.

**Fix:** Change the parameter types to `?string` (nullable) and provide default null values:

```php
public function execute(
    int $id,
    ?string $name = null,
    ?string $email = null,
    ?string $phone = null,
): ?Contact {
```

**Impact:** Any PATCH or PUT request omitting one or more fields (a valid partial update per the FormRequest) will crash with a `TypeError: Argument #2 ($name) must be of type string, null given` — making the update endpoint non-functional for partial updates. This is a production-blocking defect.

---

### CR-02: Repository save failure after processing leaves contact permanently stuck in "processing" state

**Files:**
- `src/Application/UseCases/ProcessScoreUseCase.php:26,35`
- `app/Jobs/ProcessContactScoreJob.php:16`

**Issue:** In `ProcessScoreUseCase::execute()`, the flow is:

1. Line 25: `$contact->markAsProcessing();`
2. Line 26: `$this->repository->save($contact);` — persists `status = processing` to DB
3. Lines 28-33: try-catch for score calculation
4. Line 35: `$this->repository->save($contact);` — persists final status (`active` or `failed`)

If the second `save()` at line 35 throws (DB connection drop, constraint violation, disk full, etc.), the exception propagates uncaught to `ProcessContactScoreJob::handle()`. The job has `public int $tries = 1` (line 16), so it will **never be retried**. The contact remains in `processing` state in the database permanently — it will never reach `active` or `failed`.

This is a data inconsistency: the in-memory entity has a terminal status, but the DB record is stuck in a transitional state, invisible to future processing attempts (because `canTransitionTo(Processing)` from `Processing` returns `false`).

**Fix (option A — transaction in use case, pragmatic):** Accept a Laravel DB facade in the use case and wrap the two saves in a transaction. This trades DDD purity for data consistency.

**Fix (option B — catch and retry in job, cleaner):** Wrap the use case call in the job with try-catch, and on failure, explicitly persist the failed status:

```php
public function handle(ProcessScoreUseCase $useCase): void
{
    sleep(rand(1, 2));
    try {
        $useCase->execute($this->contactId);
    } catch (\Throwable $e) {
        // Fallback: attempt to persist failure so the contact isn't stuck
        try {
            $useCase->markAsFailed($this->contactId);
        } catch (\Throwable) {
            // Logging needed — secondary failure is unrecoverable
        }
        throw $e;
    }
}
```

This requires exposing a `markAsFailed(int $contactId)` path in the use case, which fetches fresh from DB and marks failed — avoiding the need for a transaction.

---

## Warnings

### WR-01: Controller bypasses repository pattern with direct Eloquent query

**File:** `app/Http/Controllers/Api/ContactController.php:58`

**Issue:** The `index()` method calls `$total = ContactModel::count();` directly on the Eloquent model, bypassing the repository layer. This violates the DDD layer separation rule from AGENTS.md: "Domain layer must NOT import Laravel facades or ORM." While the controller is infrastructure (not domain), the `ListContactsUseCase` exists precisely to encapsulate data access.

The `ListContactsUseCase::execute()` returns only `Contact[]` — there is no mechanism to propagate pagination metadata (total count, current page, last page) without leaking ORM concerns into the application layer.

**Fix:** Either:
- Have `ListContactsUseCase` return an object or array with both items and total count, or
- Use a dedicated DTO for paginated results that is ORM-agnostic:

```php
// Domain layer
readonly class PaginatedResult {
    /** @param Contact[] $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $page,
    ) {}
}
```

Then update `ContactRepositoryInterface::findAll()` and `ListContactsUseCase::execute()` to return `PaginatedResult`, and remove the `ContactModel` import from the controller.

---

### WR-02: ProcessScoreUseCase failure path relies on score being zero by coincidence

**File:** `src/Application/UseCases/ProcessScoreUseCase.php:28-32`

**Issue:** When `$this->calculator->calculate($contact)` throws, `$contact->markAsFailed()` is called but does **not** reset the score. The `markAsFailed()` method in `src/Domain/Entities/Contact.php:177-183` only transitions the status and sets `processedAt` — the score is left unchanged.

In the current flow, `Contact::create()` initializes score to `Score::zero()` and no code path modifies it before the try-catch. Therefore the persisted score after failure is `0`, which is correct. However, this is a coincidence, not a contract.

The test `ProcessScoreUseCaseTest::test_fails_on_exception` (line 57) asserts `$this->assertSame(0, $contact->score()->value)`, which passes only because the contact entity was never modified before the mock threw. If `markAsProcessing()` ever begins to modify the score (e.g., for audit logging), or if the contact is re-processed after a fix (though not currently allowed by state transitions), the failure path would persist a stale score.

**Fix:** Explicitly reset the score in `markAsFailed()`:

```php
// In src/Domain/Entities/Contact.php
public function markAsFailed(): void
{
    $this->assertTransition(ContactStatus::Failed);
    $this->status = ContactStatus::Failed;
    $this->score = Score::zero();  // ← add this
    $this->processedAt = new \DateTimeImmutable();
    $this->touch();
}
```

---

### WR-03: Email domain prefix extraction can be bypassed by subdomains

**File:** `src/Domain/Services/Scoring/EmailDomainScoringStrategy.php:15`

**Issue:** The strategy extracts the email domain prefix with `explode('.', $contact->email()->domain())[0]`. For an email like `user@sub.gmail.com` (a subdomain of a known non-corporate provider), the first segment is `sub`, which is **not** in `NON_CORPORATE_DOMAINS`. The strategy incorrectly awards 20 corporate points.

Similarly, `user@something.gmail.com.br` would also bypass the check if the first segment is not `gmail`, `hotmail`, or `yahoo`.

While subdomains of free email providers are unusual in real-world use, this is a logic gap that could be exploited for score inflation.

**Fix:** Match the domain prefix against the second-to-last segment as well, or check if any segment matches:

```php
public function score(Contact $contact): int
{
    $points = 0;
    $domain = $contact->email()->domain();
    $parts = explode('.', $domain);
    $domainPrefix = $parts[0];

    // Also check second segment for subdomains of non-corporate providers
    // e.g., sub.gmail.com → parts = ['sub', 'gmail', 'com'] → check 'gmail'
    $isNonCorporate = in_array($domainPrefix, self::NON_CORPORATE_DOMAINS, true);
    
    if (!$isNonCorporate && count($parts) >= 3) {
        $isNonCorporate = in_array($parts[1], self::NON_CORPORATE_DOMAINS, true);
    }

    if (!$isNonCorporate) {
        $points += 20;
    }

    if ($contact->email()->tld() === 'br') {
        $points += 10;
    }

    return $points;
}
```

---

### WR-04: Queue job has no retry tolerance — `$tries = 1` with no backoff

**File:** `app/Jobs/ProcessContactScoreJob.php:16`

**Issue:** The job declares `public int $tries = 1` with no `backoff` or `retryUntil` configuration. Any transient failure (DB deadlock, Redis connection blip, network timeout) during score processing permanently fails the job and leaves the contact in its last persisted state.

Combined with CR-02 (save failure after processing), this creates a fragile pipeline where a single transient error causes irreversible data inconsistency.

**Fix (option A):** Increase retries with exponential backoff:

```php
public int $tries = 3;
public int $maxExceptions = 2;

public function backoff(): array
{
    return [2, 5, 15]; // seconds between retries
}
```

**Fix (option B):** If the job must not retry (e.g., for idempotency reasons), document the constraint explicitly and add a `failed()` method to log the failure for operational visibility:

```php
public function failed(\Throwable $e): void
{
    Log::error('Contact score processing failed permanently', [
        'contact_id' => $this->contactId,
        'error' => $e->getMessage(),
    ]);
}
```

---

## Info

### IN-01: Name length scoring uses locale-dependent word counting

**File:** `src/Domain/Services/Scoring/NameLengthScoringStrategy.php:11`

`str_word_count($contact->name())` behavior varies by PHP locale. Names with accented characters (common in Brazilian Portuguese: João, José, Célia) may produce different results on systems with ASCII vs UTF-8 locales. The test `test_full_name_adds_10_points` uses only ASCII "John Doe" and would not catch this.

Consider using a locale-independent approach:

```php
return count(preg_split('/\s+/', trim($contact->name()))) >= 2 ? 10 : 0;
```

### IN-02: Missing test coverage for job-level concerns

**Files:**
- `tests/Feature/ScoreProcessingTest.php`
- `tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php`

`ProcessContactScoreJob` has no dedicated unit test. The feature test `test_process_score_full_integration_with_sync_queue` covers the end-to-end happy path, but there is no test verifying:
- That `sleep(rand(1, 2))` is called before the use case (time-sensitive behavior)
- That a thrown exception from the use case is properly propagated
- That the job idempotently handles the same contactId twice (second call should throw DomainException from invalid state transition)

---

## Findings Summary

| ID | Severity | File | Line | Description |
|----|----------|------|------|-------------|
| CR-01 | CRITICAL | `UpdateContactUseCase.php` | 17 | Null passed to string-typed params crashes partial updates |
| CR-02 | CRITICAL | `ProcessScoreUseCase.php` | 26,35 | Save failure leaves contact stuck in "processing" |
| WR-01 | WARNING | `ContactController.php` | 58 | Direct Eloquent query bypasses repository |
| WR-02 | WARNING | `ProcessScoreUseCase.php` | 32 | Failure path score depends on coincidental zero |
| WR-03 | WARNING | `EmailDomainScoringStrategy.php` | 15 | Subdomain bypasses non-corporate check |
| WR-04 | WARNING | `ProcessContactScoreJob.php` | 16 | No retry tolerance for transient failures |
| IN-01 | INFO | `NameLengthScoringStrategy.php` | 11 | Locale-dependent word counting |
| IN-02 | INFO | Various | — | Missing job-level unit test coverage |

---

_Reviewed: 2026-05-18T12:00:00Z_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: standard_
