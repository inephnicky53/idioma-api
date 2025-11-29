<?php

namespace App\Enum;

enum SubscriptionType: string
{
    case CLUB = 'club';
    case FORMATION = 'formation';
    case BOTH = 'both';

    public function getLabel(): string
    {
        return match($this) {
            self::CLUB => 'Club',
            self::FORMATION => 'Formation',
            self::BOTH => 'Club + Formation',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::CLUB => 'Accès au club Idioma English Club',
            self::FORMATION => 'Accès aux formations en ligne',
            self::BOTH => 'Accès complet au club et aux formations',
        };
    }

    public static function getChoices(): array
    {
        return [
            self::CLUB->getLabel() => self::CLUB->value,
            self::FORMATION->getLabel() => self::FORMATION->value,
            self::BOTH->getLabel() => self::BOTH->value,
        ];
    }

    public static function fromString(string $value): self
    {
        $v = strtolower(trim($value));
        return match ($v) {
            'club', 'english club', 'idioma club' => self::CLUB,
            'formation', 'training', 'cours' => self::FORMATION,
            'both', 'all', 'complet', 'complete' => self::BOTH,
            default => self::CLUB,
        };
    }
}

