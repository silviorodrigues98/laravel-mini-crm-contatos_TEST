<?php

namespace Application\UseCases;

use Domain\Entities\Contact;
use Domain\Repositories\ContactRepositoryInterface;

class ListContactsUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
    ) {
    }

    /** @return Contact[] */
    public function execute(int $perPage = 15, int $page = 1): array
    {
        return $this->repository->findAll($perPage, $page);
    }
}
