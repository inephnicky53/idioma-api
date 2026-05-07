<?php

namespace App\Enum;

enum TransactionType: string
{
    case SUBSCRIPTION = 'subscription';
    case CLUB_MEMBERSHIP = 'club_membership';
    case TRAINING_COURSE = 'training_course';
    case REFUND = 'refund';

    public function getLabel(): string
    {
        return match($this) {
            self::SUBSCRIPTION => 'Abonnement',
            self::CLUB_MEMBERSHIP => 'Adhésion Club',
            self::TRAINING_COURSE => 'Cours de Formation',
            self::REFUND => 'Remboursement',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::SUBSCRIPTION => 'Paiement d\'un abonnement',
            self::CLUB_MEMBERSHIP => 'Paiement d\'adhésion au club',
            self::TRAINING_COURSE => 'Paiement d\'un cours de formation',
            self::REFUND => 'Remboursement de paiement',
        };
    }

    public static function getChoices(): array
    {
        return [
            self::SUBSCRIPTION->getLabel() => self::SUBSCRIPTION->value,
            self::CLUB_MEMBERSHIP->getLabel() => self::CLUB_MEMBERSHIP->value,
            self::TRAINING_COURSE->getLabel() => self::TRAINING_COURSE->value,
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
            'refund', 'remboursement', 'refund' => self::REFUND,
            default => self::SUBSCRIPTION,
        };
    }
}

