<?php

namespace App\Enum;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::PROCESSING => 'En traitement',
            self::COMPLETED => 'Complétée',
            self::FAILED => 'Échouée',
            self::CANCELLED => 'Annulée',
            self::REFUNDED => 'Remboursée',
        };
    }

    public function getCssClass(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::PROCESSING => 'info',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'dark',
            self::REFUNDED => 'secondary',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::FAILED,
            self::CANCELLED,
            self::REFUNDED,
        ]);
    }

    public static function getChoices(): array
    {
        return [
            self::PENDING->getLabel() => self::PENDING->value,
            self::PROCESSING->getLabel() => self::PROCESSING->value,
            self::COMPLETED->getLabel() => self::COMPLETED->value,
            self::FAILED->getLabel() => self::FAILED->value,
            self::CANCELLED->getLabel() => self::CANCELLED->value,
            self::REFUNDED->getLabel() => self::REFUNDED->value,
        ];
    }

    public static function fromString(string $value): self
    {
        $v = strtolower(trim($value));
        return match ($v) {
            'pending', 'en attente', 'attente' => self::PENDING,
            'processing', 'en traitement', 'traitement' => self::PROCESSING,
            'completed', 'complétée', 'complete', 'success', 'approved' => self::COMPLETED,
            'failed', 'échouée', 'echec', 'error' => self::FAILED,
            'cancelled', 'annulée', 'cancel' => self::CANCELLED,
            'refunded', 'remboursée', 'remboursee' => self::REFUNDED,
            default => self::PENDING,
        };
    }
}

