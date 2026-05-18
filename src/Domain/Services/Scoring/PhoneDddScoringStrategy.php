<?php

namespace Domain\Services\Scoring;

use Domain\Entities\Contact;

final class PhoneDddScoringStrategy implements ScoreScoringStrategy
{
    public function score(Contact $contact): int
    {
        $ddd = (int) $contact->phone()->ddd();

        if ($ddd >= 11 && $ddd <= 19) {
            return 20;
        }

        if ($ddd >= 20) {
            return 10;
        }

        return 0;
    }
}
