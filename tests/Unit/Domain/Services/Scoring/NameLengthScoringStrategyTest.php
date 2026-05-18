<?php

namespace Tests\Unit\Domain\Services\Scoring;

use Domain\Entities\Contact;
use Domain\Services\Scoring\NameLengthScoringStrategy;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;
use PHPUnit\Framework\TestCase;

class NameLengthScoringStrategyTest extends TestCase
{
    private NameLengthScoringStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new NameLengthScoringStrategy();
    }

    public function test_full_name_adds_10_points(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@company.com'),
            new Phone('11999999999')
        );

        $this->assertSame(10, $this->strategy->score($contact));
    }

    public function test_single_name_no_points(): void
    {
        $contact = Contact::create(
            'John',
            new Email('john@company.com'),
            new Phone('11999999999')
        );

        $this->assertSame(0, $this->strategy->score($contact));
    }

    public function test_multiple_words_adds_10_points(): void
    {
        $contact = Contact::create(
            'Maria José Silva',
            new Email('maria@company.com'),
            new Phone('11999999999')
        );

        $this->assertSame(10, $this->strategy->score($contact));
    }
}
