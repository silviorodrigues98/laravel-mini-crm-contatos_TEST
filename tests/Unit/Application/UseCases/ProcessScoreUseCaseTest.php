<?php

namespace Tests\Unit\Application\UseCases;

use Application\UseCases\ProcessScoreUseCase;
use Domain\Entities\Contact;
use Domain\Enums\ContactStatus;
use Domain\Repositories\ContactRepositoryInterface;
use Domain\Services\ScoreCalculator;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;
use Domain\ValueObjects\Score;
use PHPUnit\Framework\TestCase;

class ProcessScoreUseCaseTest extends TestCase
{
    public function test_processes_score_successfully(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@company.com'),
            new Phone('11999999999')
        );

        $repository = $this->createMock(ContactRepositoryInterface::class);
        $repository->method('findById')->with(1)->willReturn($contact);

        $calculator = $this->createMock(ScoreCalculator::class);
        $calculator->method('calculate')->with($contact)->willReturn(new Score(60));

        $useCase = new ProcessScoreUseCase($repository, $calculator);
        $useCase->execute(1);

        $this->assertSame(ContactStatus::Active, $contact->status());
        $this->assertSame(60, $contact->score()->value);
        $this->assertNotNull($contact->processedAt());
    }

    public function test_fails_on_exception(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@company.com'),
            new Phone('11999999999')
        );

        $repository = $this->createMock(ContactRepositoryInterface::class);
        $repository->method('findById')->with(1)->willReturn($contact);

        $calculator = $this->createMock(ScoreCalculator::class);
        $calculator->method('calculate')->with($contact)->willThrowException(new \RuntimeException('Calculation failed'));

        $useCase = new ProcessScoreUseCase($repository, $calculator);
        $useCase->execute(1);

        $this->assertSame(ContactStatus::Failed, $contact->status());
        $this->assertSame(0, $contact->score()->value);
        $this->assertNotNull($contact->processedAt());
    }
}
