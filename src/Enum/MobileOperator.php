<?php

namespace App\Enum;

enum MobileOperator: string
{
    case VODACOM = 'VODACOM';
    case AIRTEL = 'AIRTEL';
    case ORANGE = 'ORANGE';
    case AFRICELL = 'AFRICELL';

    public function getLabel(): string
    {
        return match($this) {
            self::VODACOM => 'Vodacom',
            self::AIRTEL => 'Airtel',
            self::ORANGE => 'Orange',
            self::AFRICELL => 'Africell',
        };
    }

    public static function getChoices(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}

