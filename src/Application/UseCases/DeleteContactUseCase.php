<?php

namespace Application\UseCases;

use Domain\Repositories\ContactRepositoryInterface;

class DeleteContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id): void
    {
        $this->repository->delete($id);
    }
}
