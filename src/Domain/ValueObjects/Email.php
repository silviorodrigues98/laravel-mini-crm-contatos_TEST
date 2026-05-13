<?php

namespace Domain\ValueObjects;

final readonly class Email
{
    public string $value;

    public function __construct(string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address: {$value}");
        }

        $this->value = $value;
    }

    public function domain(): string
    {
        return explode('@', $this->value)[1] ?? '';
    }

    public function tld(): string
    {
        $parts = explode('.', $this->domain());

        return end($parts);
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
