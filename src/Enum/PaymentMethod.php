<?php

namespace App\Enum;

use InvalidArgumentException;

enum PaymentMethod: string
{
    case MOBILE = 'MOBILE';
    case CARD = 'CARD';
    case CASH = 'CASH';

    public function getLabel(): string
    {
        return match($this) {
            self::MOBILE => 'Mobile Money',
            self::CARD => 'Carte Bancaire / Virement',
            self::CASH => 'Espèces',
        };
    }

    public function getLabelEn(): string
    {
        return match($this) {
            self::MOBILE => 'Mobile Money',
            self::CARD => 'Credit Card / Bank Transfer',
            self::CASH => 'Cash',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::MOBILE => 'fa-mobile-alt',
            self::CARD => 'fa-credit-card',
            self::CASH => 'fa-money-bill-wave',
        };
    }

    public function getCssClass(): string
    {
        return match($this) {
            self::MOBILE => 'info',
            self::CARD => 'purple',
            self::CASH => 'success',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::MOBILE => '#17a2b8',
            self::CARD => '#6f42c1',
            self::CASH => '#28a745',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::MOBILE => 'Paiement via Mobile Money (Vodacom, Airtel, Orange, Africell)',
            self::CARD => 'Paiement par carte bancaire ou virement bancaire via FlexPay',
            self::CASH => 'Paiement en espèces au bureau',
        };
    }

    /**
     * Retourne toutes les infos pour l'API
     */
    public function toArray(): array
    {
        return [
            'code' => $this->name,
            'value' => $this->value,
            'label' => $this->getLabel(),
            'labelEn' => $this->getLabelEn(),
            'icon' => $this->getIcon(),
            'cssClass' => $this->getCssClass(),
            'color' => $this->getColor(),
            'description' => $this->getDescription(),
        ];
    }

    public static function getChoices(): array
    {
        return [
            self::MOBILE->getLabel() => self::MOBILE->value,
            self::CARD->getLabel() => self::CARD->value,
            self::CASH->getLabel() => self::CASH->value,
        ];
    }

    public static function getAllMethods(): array
    {
        return array_map(fn($case) => $case->toArray(), self::cases());
    }

    public static function getValue(mixed $operator): PaymentMethod
    {
        return match($operator) {
            self::MOBILE->value => self::MOBILE,
            'BANK' => self::CARD,
            self::CARD->value => self::CARD,
            self::CASH->value => self::CASH,
            default => throw new InvalidArgumentException('Opérateur invalide: ' . $operator),
        };
    }

    public static function fromString(string $value): PaymentMethod
    {
        return match(strtoupper($value)) {
            'MOBILE' => self::MOBILE,
            'BANK' => self::CARD,
            'CARD' => self::CARD,
            'CASH' => self::CASH,
            default => throw new InvalidArgumentException('Méthode de paiement invalide: ' . $value),
        };
    }

    public static function formatPhoneNumber(string|int $phone): string
    {
        // Nettoyer les espaces, tirets, parenthèses
        $cleaned = preg_replace('/[\s\-()]+/', '', $phone);

        $prefix = '243';

        // Pattern pour détecter les différents formats
        $patterns = [
            "/^\+$prefix([0-9]{9})$/" => "$prefix$1",           // +243XXXXXXXXX
            "/^00$prefix([0-9]{9})$/" => "$prefix$1",           // 00243XXXXXXXXX
            "/^0([0-9]{9})$/" => "$prefix$1",                   // 0XXXXXXXXX
            "/^$prefix([0-9]{9})$/" => "$prefix$1",             // 243XXXXXXXXX (déjà bon)
            "/^([0-9]{9})$/" => "$prefix$1",                    // XXXXXXXXX
        ];

        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $cleaned)) {
                return preg_replace($pattern, $replacement, $cleaned);
            }
        }
        return $prefix . $cleaned;
    }
}
