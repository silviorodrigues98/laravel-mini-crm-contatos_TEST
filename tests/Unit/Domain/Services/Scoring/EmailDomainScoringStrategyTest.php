<?php

namespace Tests\Unit\Domain\Services\Scoring;

use Domain\Entities\Contact;
use Domain\Services\Scoring\EmailDomainScoringStrategy;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;
use PHPUnit\Framework\TestCase;

class EmailDomainScoringStrategyTest extends TestCase
{
    private EmailDomainScoringStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new EmailDomainScoringStrategy();
    }

    public function test_corporate_email_adds_20_points(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@company.com'),
            new Phone('11999999999')
        );

        $this->assertSame(20, $this->strategy->score($contact));
    }

    public function test_br_tld_adds_10_points(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@gmail.com.br'),
            new Phone('11999999999')
        );

        $this->assertSame(10, $this->strategy->score($contact));
    }

    public function test_gmail_email_no_points(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@gmail.com'),
            new Phone('11999999999')
        );

        $this->assertSame(0, $this->strategy->score($contact));
    }

    public function test_gmail_br_only_br_bonus(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@gmail.com.br'),
            new Phone('11999999999')
        );

        $this->assertSame(10, $this->strategy->score($contact));
    }

    public function test_hotmail_email_no_points(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@hotmail.com'),
            new Phone('11999999999')
        );

        $this->assertSame(0, $this->strategy->score($contact));
    }
}
