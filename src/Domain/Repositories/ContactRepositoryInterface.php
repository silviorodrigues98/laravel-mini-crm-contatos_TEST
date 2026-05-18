<?php

namespace Domain\Repositories;

use Domain\Entities\Contact;
use Domain\ValueObjects\PaginatedResult;

interface ContactRepositoryInterface
{
    public function save(Contact $contact): void;
    public function findById(int $id): ?Contact;
    public function findAll(int $perPage = 15, int $page = 1): PaginatedResult;
    public function delete(int $id): void;
}
