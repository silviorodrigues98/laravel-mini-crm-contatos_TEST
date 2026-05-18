<?php

namespace Application\UseCases;

use Domain\Entities\Contact;
use Domain\Repositories\ContactRepositoryInterface;
use Domain\Services\ScoreCalculator;

class ProcessScoreUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
        private readonly ScoreCalculator $calculator,
    ) {
    }

    public function execute(int $contactId): void
    {
        $contact = $this->repository->findById($contactId);

        if ($contact === null) {
            throw new \RuntimeException("Contact not found: {$contactId}");
        }

        $contact->markAsProcessing();
        $this->repository->save($contact);

        try {
            $score = $this->calculator->calculate($contact);
            $contact->markAsActive($score);
        } catch (\Throwable) {
            $contact->markAsFailed();
        }

        $this->repository->save($contact);
    }
}
