<?php

namespace Tests\Unit\Domain\Services\Scoring;

use Domain\Entities\Contact;
use Domain\Services\Scoring\PhoneDddScoringStrategy;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;
use PHPUnit\Framework\TestCase;

class PhoneDddScoringStrategyTest extends TestCase
{
    private PhoneDddScoringStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new PhoneDddScoringStrategy();
    }

    public function test_sp_ddd_adds_20_points(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@company.com'),
            new Phone('11999999999')
        );

        $this->assertSame(20, $this->strategy->score($contact));
    }

    public function test_sp_ddd_upper_boundary(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@company.com'),
            new Phone('19999999999')
        );

        $this->assertSame(20, $this->strategy->score($contact));
    }

    public function test_other_state_ddd_adds_10_points(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@company.com'),
            new Phone('21999999999')
        );

        $this->assertSame(10, $this->strategy->score($contact));
    }

    public function test_invalid_ddd_no_points(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@company.com'),
            new Phone('10999999999')
        );

        $this->assertSame(0, $this->strategy->score($contact));
    }

    public function test_after_sp_boundary_is_other_state(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@company.com'),
            new Phone('20999999999')
        );

        $this->assertSame(10, $this->strategy->score($contact));
    }
}
