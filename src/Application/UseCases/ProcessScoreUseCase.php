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

    public function execute(int $contactId): Contact
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

        return $contact;
    }

    /**
     * Fallback: persist a failed status for a contact.
     * Used by ProcessContactScoreJob when execute() throws after
     * the processing state has already been persisted, to avoid
     * leaving the contact permanently stuck in "processing".
     */
    public function markAsFailed(int $contactId): void
    {
        $contact = $this->repository->findById($contactId);

        if ($contact === null) {
            return;
        }

        $contact->markAsFailed();
        $this->repository->save($contact);
    }
}
