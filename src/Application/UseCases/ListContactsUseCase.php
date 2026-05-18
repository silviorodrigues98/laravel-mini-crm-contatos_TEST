<?php

namespace Application\UseCases;

use Domain\Repositories\ContactRepositoryInterface;
use Domain\ValueObjects\PaginatedResult;

class ListContactsUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
    ) {
    }

    public function execute(int $perPage = 15, int $page = 1): PaginatedResult
    {
        return $this->repository->findAll($perPage, $page);
    }
}
