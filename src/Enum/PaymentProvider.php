<?php

namespace App\Enum;

use InvalidArgumentException;

enum PaymentProvider: string
{
    case FLEXPAY = 'flexpay';
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';

    public function getLabel(): string
    {
        return match($this) {
            self::FLEXPAY => 'FlexPay',
            self::STRIPE => 'Stripe',
            self::PAYPAL => 'PayPal',
        };
    }

    public function getLabelEn(): string
    {
        return $this->getLabel();
    }

    public function getIcon(): string
    {
        return match($this) {
            self::FLEXPAY => 'fa-mobile-alt',
            self::STRIPE => 'fa-credit-card',
            self::PAYPAL => 'fa-paypal',
        };
    }

    public function getCssClass(): string
    {
        return match($this) {
            self::FLEXPAY => 'info',
            self::STRIPE => 'primary',
            self::PAYPAL => 'warning',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::FLEXPAY => '#17a2b8',
            self::STRIPE => '#6772e5',
            self::PAYPAL => '#003087',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::FLEXPAY => 'Paiement Mobile Money (Vodacom, Airtel, Orange, Africell)',
            self::STRIPE => 'Paiement par carte bancaire internationale',
            self::PAYPAL => 'Paiement via compte PayPal',
        };
    }

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
            self::FLEXPAY->getLabel() => self::FLEXPAY->value,
            self::STRIPE->getLabel() => self::STRIPE->value,
            self::PAYPAL->getLabel() => self::PAYPAL->value,
        ];
    }

    public static function getAllProviders(): array
    {
        return array_map(fn($case) => $case->toArray(), self::cases());
    }

    public static function fromString(string $value): self
    {
        $v = strtolower(trim($value));
        return match ($v) {
            'flexpay', 'flex_pay', 'flex-pay' => self::FLEXPAY,
            'stripe' => self::STRIPE,
            'paypal', 'pay_pal', 'pay-pal' => self::PAYPAL,
            default => throw new InvalidArgumentException("Provider de paiement invalide: $value"),
        };
    }
}

