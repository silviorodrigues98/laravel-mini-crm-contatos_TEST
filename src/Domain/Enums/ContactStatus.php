<?php

namespace Domain\Enums;

enum ContactStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Active = 'active';
    case Failed = 'failed';

    public function canTransitionTo(ContactStatus $target): bool
    {
        return match ($this) {
            self::Pending => match ($target) {
                self::Processing => true,
                default => false,
            },
            self::Processing => match ($target) {
                self::Active, self::Failed => true,
                default => false,
            },
            self::Active, self::Failed => false,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
