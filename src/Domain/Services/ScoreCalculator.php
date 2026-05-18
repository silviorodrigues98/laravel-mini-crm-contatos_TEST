<?php

namespace Domain\Services;

use Domain\Entities\Contact;
use Domain\Services\Scoring\ScoreScoringStrategy;
use Domain\ValueObjects\Score;

class ScoreCalculator
{
    /**
     * @param ScoreScoringStrategy[] $strategies
     */
    public function __construct(
        private readonly array $strategies,
    ) {
    }

    public function calculate(Contact $contact): Score
    {
        $total = 0;

        foreach ($this->strategies as $strategy) {
            $total += $strategy->score($contact);
        }

        return new Score($total);
    }
}
