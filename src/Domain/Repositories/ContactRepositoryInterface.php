<?php

namespace Domain\Repositories;

use Domain\Entities\Contact;

interface ContactRepositoryInterface
{
    public function save(Contact $contact): void;
    public function findById(int $id): ?Contact;
    /** @return Contact[] */
    public function findAll(int $perPage = 15, int $page = 1): array;
    public function delete(int $id): void;
}
