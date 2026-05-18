<?php

namespace Domain\Services\Scoring;

use Domain\Entities\Contact;

final class NameLengthScoringStrategy implements ScoreScoringStrategy
{
    public function score(Contact $contact): int
    {
        return str_word_count($contact->name()) >= 2 ? 10 : 0;
    }
}
