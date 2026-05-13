<?php

namespace Domain\Entities;

use Domain\Enums\ContactStatus;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;
use Domain\ValueObjects\Score;

class Contact
{
    private ?int $id;
    private string $name;
    private Email $email;
    private Phone $phone;
    private Score $score;
    private ContactStatus $status;
    private ?\DateTimeImmutable $processedAt;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;
    private ?\DateTimeImmutable $deletedAt;

    private function __construct()
    {
    }

    public static function create(string $name, Email $email, Phone $phone): self
    {
        $contact = new self();
        $contact->name = $name;
        $contact->email = $email;
        $contact->phone = $phone;
        $contact->score = Score::zero();
        $contact->status = ContactStatus::Pending;
        $contact->processedAt = null;
        $contact->createdAt = new \DateTimeImmutable();
        $contact->updatedAt = null;
        $contact->deletedAt = null;

        return $contact;
    }

    public static function reconstitute(
        ?int $id,
        string $name,
        Email $email,
        Phone $phone,
        Score $score,
        ContactStatus $status,
        ?\DateTimeImmutable $processedAt,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
        ?\DateTimeImmutable $deletedAt
    ): self {
        $contact = new self();
        $contact->id = $id;
        $contact->name = $name;
        $contact->email = $email;
        $contact->phone = $phone;
        $contact->score = $score;
        $contact->status = $status;
        $contact->processedAt = $processedAt;
        $contact->createdAt = $createdAt;
        $contact->updatedAt = $updatedAt;
        $contact->deletedAt = $deletedAt;

        return $contact;
    }

    private function assertTransition(ContactStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(
                "Cannot transition from {$this->status->value} to {$target->value}."
            );
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function phone(): Phone
    {
        return $this->phone;
    }

    public function score(): Score
    {
        return $this->score;
    }

    public function status(): ContactStatus
    {
        return $this->status;
    }

    public function processedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function updateEmail(Email $email): void
    {
        $this->email = $email;
        $this->touch();
    }

    public function changePhone(Phone $phone): void
    {
        $this->phone = $phone;
        $this->touch();
    }

    public function markAsProcessing(): void
    {
        $this->assertTransition(ContactStatus::Processing);
        $this->status = ContactStatus::Processing;
        $this->touch();
    }

    public function markAsActive(Score $score): void
    {
        $this->assertTransition(ContactStatus::Active);
        $this->status = ContactStatus::Active;
        $this->score = $score;
        $this->processedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function markAsFailed(): void
    {
        $this->assertTransition(ContactStatus::Failed);
        $this->status = ContactStatus::Failed;
        $this->processedAt = new \DateTimeImmutable();
        $this->touch();
    }
}
