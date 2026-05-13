<?php

namespace Domain\ValueObjects;

final readonly class Score
{
    public int $value;

    public function __construct(int $value)
    {
        if ($value < 0) {
            throw new \InvalidArgumentException("Score cannot be negative: {$value}");
        }

        $this->value = $value;
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
