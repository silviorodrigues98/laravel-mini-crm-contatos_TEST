<?php

namespace Domain\Services\Scoring;

use Domain\Entities\Contact;

final class EmailDomainScoringStrategy implements ScoreScoringStrategy
{
    private const NON_CORPORATE_DOMAINS = ['gmail', 'hotmail', 'yahoo'];

    public function score(Contact $contact): int
    {
        $points = 0;

        $domainPrefix = explode('.', $contact->email()->domain())[0];

        if (!in_array($domainPrefix, self::NON_CORPORATE_DOMAINS, true)) {
            $points += 20;
        }

        if ($contact->email()->tld() === 'br') {
            $points += 10;
        }

        return $points;
    }
}
