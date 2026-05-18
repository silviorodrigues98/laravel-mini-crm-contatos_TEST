<?php

namespace Domain\Services\Scoring;

use Domain\Entities\Contact;

final class EmailDomainScoringStrategy implements ScoreScoringStrategy
{
    private const NON_CORPORATE_DOMAINS = ['gmail', 'hotmail', 'yahoo'];

    public function score(Contact $contact): int
    {
        $points = 0;

        $parts = explode('.', $contact->email()->domain());

        // Check if any domain segment matches a known non-corporate provider.
        // This prevents subdomain bypass (e.g., sub.gmail.com → parts[0]='sub'
        // would miss the check, but parts[1]='gmail' correctly matches).
        $isNonCorporate = false;
        foreach ($parts as $segment) {
            if (in_array($segment, self::NON_CORPORATE_DOMAINS, true)) {
                $isNonCorporate = true;
                break;
            }
        }

        if (!$isNonCorporate) {
            $points += 20;
        }

        if ($contact->email()->tld() === 'br') {
            $points += 10;
        }

        return $points;
    }
}
