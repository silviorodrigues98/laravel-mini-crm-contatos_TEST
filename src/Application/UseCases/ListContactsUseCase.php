<?php

namespace Application\UseCases;

use Domain\Repositories\ContactRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListContactsUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
    ) {
    }

    public function execute(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->repository->findAll($perPage, $page);
    }
}
