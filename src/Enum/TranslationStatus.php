<?php

namespace App\Enum;

enum TranslationStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::IN_PROGRESS => 'En cours',
            self::COMPLETED => 'Terminé',
            self::CANCELLED => 'Annulé',
        };
    }

    public function getLabelEn(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getBadgeColor(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::IN_PROGRESS => 'info',
            self::COMPLETED => 'success',
            self::CANCELLED => 'secondary',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::PENDING => 'fa-clock',
            self::IN_PROGRESS => 'fa-spinner fa-spin',
            self::COMPLETED => 'fa-check-circle',
            self::CANCELLED => 'fa-times-circle',
        };
    }

    public static function getChoices(): array
    {
        return [
            self::PENDING->getLabel() => self::PENDING->value,
            self::IN_PROGRESS->getLabel() => self::IN_PROGRESS->value,
            self::COMPLETED->getLabel() => self::COMPLETED->value,
            self::CANCELLED->getLabel() => self::CANCELLED->value,
        ];
    }

    public static function fromString(string $value): self
    {
        $v = strtolower(trim($value));
        return match ($v) {
            'pending', 'en attente', 'attente', 'new' => self::PENDING,
            'in_progress', 'in progress', 'en cours', 'en_cours', 'progress' => self::IN_PROGRESS,
            'completed', 'complétée', 'complete', 'success', 'terminé', 'termine' => self::COMPLETED,
            'cancelled', 'annulée', 'cancel', 'annule' => self::CANCELLED,
            default => self::PENDING,
        };
    }
}

