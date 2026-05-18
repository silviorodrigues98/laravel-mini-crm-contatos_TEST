---
phase: 01-domain-foundation-crud
reviewed: 2026-05-18T12:00:00Z
depth: standard
files_reviewed: 24
files_reviewed_list:
  - app/Http/Controllers/Api/ContactController.php
  - app/Http/Requests/StoreContactRequest.php
  - app/Http/Requests/UpdateContactRequest.php
  - app/Http/Resources/ContactCollection.php
  - app/Http/Resources/ContactResource.php
  - app/Infrastructure/Models/Contact.php
  - app/Infrastructure/Observers/ContactObserver.php
  - app/Infrastructure/Persistence/Mappers/ContactMapper.php
  - app/Infrastructure/Repositories/EloquentContactRepository.php
  - app/Providers/AppServiceProvider.php
  - composer.json
  - database/factories/ContactFactory.php
  - database/migrations/2026_05_13_015102_create_contacts_table.php
  - phpunit.xml
  - routes/api.php
  - src/Application/UseCases/CreateContactUseCase.php
  - src/Application/UseCases/DeleteContactUseCase.php
  - src/Application/UseCases/GetContactUseCase.php
  - src/Application/UseCases/ListContactsUseCase.php
  - src/Application/UseCases/UpdateContactUseCase.php
  - src/Domain/Entities/Contact.php
  - src/Domain/Enums/ContactStatus.php
  - src/Domain/Repositories/ContactRepositoryInterface.php
  - src/Domain/ValueObjects/Email.php
  - src/Domain/ValueObjects/Phone.php
  - src/Domain/ValueObjects/Score.php
  - tests/Feature/ContactApiTest.php
findings:
  critical: 2
  warning: 4
  info: 4
  total: 10
status: issues_found
---

# Phase 01: Code Review Report — Domain Foundation & CRUD

**Reviewed:** 2026-05-18T12:00:00Z
**Depth:** standard
**Files Reviewed:** 26
**Status:** issues_found

## Summary

Code review of the CRUD foundation phase for the Mini CRM de Contatos API. The implementation follows DDD layering and TDD patterns, with clean separation between Domain, Application, and Infrastructure. However, two **blocker** issues were found: a direct DDD layer violation where the Domain repository interface imports a Laravel ORM class, and a serialization bug in `ContactCollection` that causes list endpoint responses to produce empty objects. Several warnings around error handling, validation, and coverage configuration are also present.

## Critical Issues

### CR-01: Domain layer imports Laravel ORM class — DDD constraint violation

**File:** `src/Domain/Repositories/ContactRepositoryInterface.php:6`
**File:** `src/Application/UseCases/ListContactsUseCase.php:6`

**Issue:** `ContactRepositoryInterface` imports `Illuminate\Contracts\Pagination\LengthAwarePaginator` from the Laravel framework and uses it as a return type for `findAll()`. The `ListContactsUseCase` also re-exports this type. This directly violates the project's explicit DDD constraint (AGENTS.md lines 10 and 33): *"Domain layer must NOT import Laravel facades or ORM"* and *"Domain agnostic to framework"*.

The Domain layer should be completely decoupled from the framework, but the pagination contract ties it to Laravel's specific paginator interface. If the framework were swapped, the domain interface would break.

**Fix:** Define an application-level pagination DTO/collection, or return a plain `array` of `Contact` entities from the interface and let the Infrastructure layer handle pagination at the controller level. For example:

```php
// src/Domain/Repositories/ContactRepositoryInterface.php
public function findAll(int $perPage = 15, int $page = 1): array;
```

Then in the controller or a dedicated application service, wrap the array into Laravel's paginator for the response. Alternatively, create a simple `PaginatedCollection` value object in the Application layer that is framework-agnostic.

---

### CR-02: ContactCollection.toArray returns unwrapped domain entities — JSON serialization produces empty objects

**File:** `app/Http/Resources/ContactCollection.php:12-17`

**Issue:** `ContactCollection` extends `ResourceCollection` and overrides `toArray()` to return `['data' => $this->collection]`. The `$this->collection` property contains raw `Contact` domain entities from the paginator. Since `Contact` entities have all-private properties, PHP's `json_encode` serializes them as `{}` (empty objects).

The `$collects = ContactResource::class` property is declared, but it is only consulted by the **default** `ResourceCollection::toArray()` — the override bypasses it entirely, so no wrapping occurs.

The existing test (`test_can_list_contacts_with_pagination`) only checks `assertJsonStructure(['data' => []])`, which passes even when data items are empty objects because it only validates that `data` is an array. A test asserting actual field values would fail.

**Fix:** Remove the `toArray()` override entirely to use the default implementation from `ResourceCollection`, which automatically wraps each item via `$collects`:

```php
class ContactCollection extends ResourceCollection
{
    public $collects = ContactResource::class;
}
```

If custom metadata is needed, call parent:

```php
public function toArray(Request $request): array
{
    return array_merge(parent::toArray($request), [
        'meta' => [...],
    ]);
}
```

---

## Warnings

### WR-01: UpdateContactUseCase throws DomainException — produces 500 instead of 404

**File:** `src/Application/UseCases/UpdateContactUseCase.php:22`
**File:** `app/Http/Controllers/Api/ContactController.php:64-76`

**Issue:** When the contact to update is not found, `UpdateContactUseCase::execute()` throws a `\DomainException`. This exception is not mapped to an HTTP 404 in Laravel's exception handler, so it results in a generic 500 error response. The controller's `show()` method properly handles the null case with a 404 response, but `update()` does not.

The `destroy()` method avoids this because it uses `findOrFail()` (which throws `ModelNotFoundException` — automatically converted to 404). The update path is inconsistent.

**Fix:** Either:
1. Return null from `UpdateContactUseCase` and check in the controller (consistent with `GetContactUseCase`):
```php
// UpdateContactUseCase.php
public function execute(int $id, ?string $name = null, ?string $email = null, ?string $phone = null): ?Contact
{
    $contact = $this->repository->findById($id);
    if ($contact === null) {
        return null;
    }
    // ...
}

// ContactController.php
public function update(UpdateContactRequest $request, int $id): JsonResponse
{
    $data = $request->validated();
    $contact = $this->updateContact->execute($id, ...);
    if ($contact === null) {
        return response()->json(['message' => 'Not Found'], 404);
    }
    return ContactResource::make($contact)->response();
}
```

Or 2. Register a custom exception mapping in `bootstrap/app.php` for `\DomainException`.

---

### WR-02: Empty/whitespace name accepted by Domain entity

**File:** `src/Domain/Entities/Contact.php:27-42`
**File:** `src/Domain/Entities/Contact.php:135-139`

**Issue:** The `Contact::create()` and `Contact::updateName()` methods accept any string value without validation. While the Form Request layer enforces `'required' | 'string' | 'max:255'`, the Domain entity itself does not guard against empty strings (`''`) or whitespace-only strings (`'   '`). This violates the DDD principle that entities should be self-validating regardless of which layer calls them.

**Fix:** Add validation in the domain entity:

```php
public static function create(string $name, Email $email, Phone $phone): self
{
    if (trim($name) === '') {
        throw new \InvalidArgumentException('Contact name cannot be empty.');
    }
    // ...
}

public function updateName(string $name): void
{
    if (trim($name) === '') {
        throw new \InvalidArgumentException('Contact name cannot be empty.');
    }
    $this->name = $name;
    $this->touch();
}
```

---

### WR-03: phpunit.xml source coverage excludes src/ directory

**File:** `phpunit.xml:15-19`

**Issue:** The `<source>` element in `phpunit.xml` only includes the `app` directory for code coverage analysis. The `src/` directory (containing Domain entities, Value Objects, Enums, Repository interfaces, and Application Use Cases) is excluded. This means code coverage reports will not capture Domain or Application layer coverage, defeating one of the key TDD goals stated in the project spec.

**Fix:** Add the `src` directory to the source include:

```xml
<source>
    <include>
        <directory>app</directory>
        <directory>src</directory>
    </include>
</source>
```

---

### WR-04: Controller calls $request->validated() repeatedly

**File:** `app/Http/Controllers/Api/ContactController.php:32-36`

**Issue:** In `store()`, `$request->validated()` is called three times (once for each field). While functionally correct (each call returns the same data), this is wasteful — `validated()` re-extracts and re-filters the validated data each time. It also makes the code harder to read and maintain.

**Fix:** Store the result in a variable:

```php
public function store(StoreContactRequest $request): JsonResponse
{
    $data = $request->validated();

    $contact = $this->createContact->execute(
        $data['name'],
        $data['email'],
        $data['phone'],
    );

    return ContactResource::make($contact)
        ->response()
        ->setStatusCode(201);
}
```

---

## Info

### IN-01: No authentication middleware on API routes

**File:** `routes/api.php:6`

**Issue:** The `contacts` API resource routes have no authentication middleware, and both Form Requests have `authorize()` returning `true`. While this may be intentional for the MVP phase, `laravel/sanctum` is included in `composer.json`. Without any auth, the API is fully open. Consider adding Sanctum middleware once authentication requirements are defined.

---

### IN-02: Duplicate phone normalization logic across layers

**Files:**
- `app/Http/Requests/StoreContactRequest.php:25-28`
- `app/Http/Requests/UpdateContactRequest.php:25-28`
- `app/Infrastructure/Observers/ContactObserver.php:9-14`

**Issue:** Phone normalization (stripping non-digits via `preg_replace('/\D/', '', ...)`) is implemented in three separate places: both Form Requests' `prepareForValidation()` and the Eloquent model Observer's `saving()` hook. The Observer acts as defense-in-depth, but the duplication means changes to normalization logic must be synchronized across three files. If only one is updated, behavior diverges. Consider centralizing normalization into a single service or the `Phone` value object itself.

---

### IN-03: Phone regex validation rule is redundant after prepareForValidation

**Files:**
- `app/Http/Requests/StoreContactRequest.php:20`
- `app/Http/Requests/UpdateContactRequest.php:20`

**Issue:** Both Form Requests apply `regex:/^[\d\s\-\(\)\+]+$/` to the `phone` field. However, `prepareForValidation()` runs first and strips all non-digit characters via `preg_replace('/\D/', '', ...)`. By the time the regex rule runs, the phone value contains only digits — the regex is always satisfied. The regex rule can be removed or replaced with a more meaningful validation (e.g., `digits:10` or `min:10`).

---

### IN-04: Reflection used to set private entity ID in repository

**File:** `app/Infrastructure/Repositories/EloquentContactRepository.php:24-28`

**Issue:** After saving a new contact, the repository uses `ReflectionProperty` to set the private `id` field on the domain entity. While this is a recognized pattern for persistence-ignorant entities (IDs come from the database), it bypasses encapsulation. Consider adding a package-private or internal `setId()` method, or handle ID assignment within the entity's factory/reconstitute methods.

---

_Reviewed: 2026-05-18T12:00:00Z_
_Reviewer: gsd-code-reviewer (standard depth)_
_Depth: standard_
