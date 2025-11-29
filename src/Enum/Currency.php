<?php

namespace App\Enum;

enum Currency: string
{
    case USD = 'USD';
    case CDF = 'CDF';

    public function getLabel(): string
    {
        return match($this) {
            self::USD => 'Dollar Américain (USD)',
            self::CDF => 'Francs Congolais (CDF)',
        };
    }

    /**
     * Pour EasyAdmin ChoiceField - retourne [label => enum]
     */
    public static function getChoices(): array
    {
        return [
            self::USD->getLabel() => self::USD,
            self::CDF->getLabel() => self::CDF,
        ];
    }

    public function getSymbol(): string
    {
        return match($this) {
            self::USD => '$',
            self::CDF => 'FC',
        };
    }
}
