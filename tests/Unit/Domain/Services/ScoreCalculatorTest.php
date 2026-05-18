<?php

namespace Tests\Unit\Domain\Services;

use Domain\Entities\Contact;
use Domain\Services\Scoring\ScoreScoringStrategy;
use Domain\Services\ScoreCalculator;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;
use PHPUnit\Framework\TestCase;

class ScoreCalculatorTest extends TestCase
{
    public function test_calculates_sum_of_all_strategies(): void
    {
        $strategy1 = $this->createMock(ScoreScoringStrategy::class);
        $strategy1->method('score')->willReturn(20);

        $strategy2 = $this->createMock(ScoreScoringStrategy::class);
        $strategy2->method('score')->willReturn(10);

        $calculator = new ScoreCalculator([$strategy1, $strategy2]);

        $contact = Contact::create(
            'John Doe',
            new Email('john@company.com'),
            new Phone('11999999999')
        );

        $score = $calculator->calculate($contact);

        $this->assertSame(30, $score->value);
    }

    public function test_empty_strategies_returns_zero(): void
    {
        $calculator = new ScoreCalculator([]);

        $contact = Contact::create(
            'John Doe',
            new Email('john@company.com'),
            new Phone('11999999999')
        );

        $score = $calculator->calculate($contact);

        $this->assertSame(0, $score->value);
    }
}
