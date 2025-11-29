<?php

namespace App\Enum;

enum PurchaseType: string
{
    case SUBSCRIPTION_CLUB = 'subscription_club';
    case COURSE = 'course';

    public function getLabel(): string
    {
        return match($this) {
            self::SUBSCRIPTION_CLUB => 'Abonnement',
            self::COURSE => 'Cours',
        };
    }

    public function getLabelEn(): string
    {
        return match($this) {
            self::SUBSCRIPTION_CLUB => 'Subscription Club',
            self::COURSE => 'Course',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::SUBSCRIPTION_CLUB => 'fa-calendar-check',
            self::COURSE => 'fa-graduation-cap',
        };
    }

    public function getCssClass(): string
    {
        return match($this) {
            self::SUBSCRIPTION_CLUB => 'primary',
            self::COURSE => 'info',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::SUBSCRIPTION_CLUB => '#007bff',
            self::COURSE => '#17a2b8',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::SUBSCRIPTION_CLUB => 'Abonnement mensuel ou annuel avec accès aux sessions',
            self::COURSE => 'Achat unique d\'un cours avec vidéos et ebook',
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
            self::SUBSCRIPTION_CLUB->getLabel() => self::SUBSCRIPTION_CLUB->value,
            self::COURSE->getLabel() => self::COURSE->value,
        ];
    }

    public static function fromString(string $value): self
    {
        $v = strtolower(trim($value));
        return match ($v) {
            'course', 'cours' => self::COURSE,
            default => self::SUBSCRIPTION_CLUB,
        };
    }
}

