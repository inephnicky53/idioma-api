<?php

namespace App\Enum;

use InvalidArgumentException;

enum PaymentMethod: string
{
    case MOBILE = 'MOBILE';
    case BANK = 'BANK';
    case CASH = 'CASH';

    public function getLabel(): string
    {
        return match($this) {
            self::MOBILE => 'Mobile Money',
            self::BANK => 'Virement Bancaire',
            self::CASH => 'Espèces',
        };
    }

    public function getLabelEn(): string
    {
        return match($this) {
            self::MOBILE => 'Mobile Money',
            self::BANK => 'Bank Transfer',
            self::CASH => 'Cash',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::MOBILE => 'fa-mobile-alt',
            self::BANK => 'fa-university',
            self::CASH => 'fa-money-bill-wave',
        };
    }

    public function getCssClass(): string
    {
        return match($this) {
            self::MOBILE => 'info',
            self::BANK => 'primary',
            self::CASH => 'success',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::MOBILE => '#17a2b8',
            self::BANK => '#007bff',
            self::CASH => '#28a745',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::MOBILE => 'Paiement via Mobile Money (Vodacom, Airtel, Orange, Africell)',
            self::BANK => 'Virement bancaire ou carte de crédit',
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
            self::BANK->getLabel() => self::BANK->value,
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
            self::BANK->value => self::BANK,
            self::CASH->value => self::CASH,
            default => throw new InvalidArgumentException('Opérateur invalide: ' . $operator),
        };
    }

    public static function fromString(string $value): PaymentMethod
    {
        return match(strtoupper($value)) {
            'MOBILE' => self::MOBILE,
            'BANK' => self::BANK,
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
