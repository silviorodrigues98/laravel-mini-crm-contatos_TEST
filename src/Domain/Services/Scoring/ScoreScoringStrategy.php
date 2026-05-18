<?php

namespace Domain\Services\Scoring;

use Domain\Entities\Contact;

interface ScoreScoringStrategy
{
    public function score(Contact $contact): int;
}
