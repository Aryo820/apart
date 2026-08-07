<?php

namespace App\Enums;

enum ApartmentStatus: string
{
    case Available = 'available';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Maintenance => 'Maintenance',
        };
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
