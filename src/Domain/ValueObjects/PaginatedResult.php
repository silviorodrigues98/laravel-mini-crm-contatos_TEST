<?php

namespace Domain\ValueObjects;

use Domain\Entities\Contact;

/**
 * ORM-agnostic paginated result DTO.
 * Carries items, total count, and pagination metadata so that
 * infrastructure code (controllers) can construct framework-specific
 * paginators without leaking ORM concerns into the application or
 * domain layers.
 */
readonly class PaginatedResult
{
    /** @param Contact[] $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $page,
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }
}
