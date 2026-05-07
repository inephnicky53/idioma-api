<?php

namespace App\Enum;

enum PaymentType: string
{
    case SUBSCRIPTION = 'subscription';
    case CLUB_MEMBERSHIP = 'club_membership';
    case TRAINING_COURSE = 'training_course';
    case SESSION = 'session';
    case REFUND = 'refund';

    public function getLabel(): string
    {
        return match($this) {
            self::SUBSCRIPTION => 'Abonnement',
            self::CLUB_MEMBERSHIP => 'Adhésion Club',
            self::TRAINING_COURSE => 'Cours de Formation',
            self::SESSION => 'Session',
            self::REFUND => 'Remboursement',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::SUBSCRIPTION => 'Paiement d\'un abonnement mensuel ou annuel',
            self::CLUB_MEMBERSHIP => 'Paiement d\'adhésion au club Idioma English Club',
            self::TRAINING_COURSE => 'Paiement d\'un cours de formation en ligne',
            self::SESSION => 'Paiement d\'une session individuelle',
            self::REFUND => 'Remboursement de paiement',
        };
    }

    public static function getChoices(): array
    {
        return [
            self::SUBSCRIPTION->getLabel() => self::SUBSCRIPTION->value,
            self::CLUB_MEMBERSHIP->getLabel() => self::CLUB_MEMBERSHIP->value,
            self::TRAINING_COURSE->getLabel() => self::TRAINING_COURSE->value,
            self::SESSION->getLabel() => self::SESSION->value,
            self::REFUND->getLabel() => self::REFUND->value,
        ];
    }

    public static function fromString(string $value): self
    {
        $v = strtolower(trim($value));
        return match ($v) {
            'subscription', 'abonnement', 'sub' => self::SUBSCRIPTION,
            'club', 'club_membership', 'adhésion', 'adhesion' => self::CLUB_MEMBERSHIP,
            'training', 'course', 'formation', 'training_course' => self::TRAINING_COURSE,
            'session', 'individual', 'particulier' => self::SESSION,
            'refund', 'remboursement' => self::REFUND,
            default => self::SUBSCRIPTION,
        };
    }
}

