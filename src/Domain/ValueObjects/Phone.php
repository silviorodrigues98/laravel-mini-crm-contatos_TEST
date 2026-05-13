<?php

namespace Domain\ValueObjects;

final readonly class Phone
{
    public string $value;

    public function __construct(string $value)
    {
        if (!ctype_digit($value) || strlen($value) < 10) {
            throw new \InvalidArgumentException("Invalid phone number: {$value}");
        }

        $this->value = $value;
    }

    public function ddd(): string
    {
        return substr($this->value, 0, 2);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
