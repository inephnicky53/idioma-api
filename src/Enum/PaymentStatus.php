<?php

namespace App\Enum;

enum PaymentStatus: string
{
    case INIT = 'Initialisation';
    case WAIT = 'Attente de paiement';
    case PROCESS = 'En cours';
    case COMPLETED = 'Complété';
    case FAILED = 'Echec';
    case REJECTED = 'Rejeté';
    case ERROR = 'Erreur';
    case REFUNDED = 'Remboursé';
    case CANCELLED = 'Annulé';

    public function getLabel(): string
    {
        return $this->value;
    }

    /**
     * Label en anglais pour l'API
     */
    public function getLabelEn(): string
    {
        return match($this) {
            self::INIT => 'Initialization',
            self::WAIT => 'Pending Payment',
            self::PROCESS => 'Processing',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::REJECTED => 'Rejected',
            self::ERROR => 'Error',
            self::REFUNDED => 'Refunded',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Classe CSS Bootstrap pour les badges
     */
    public function getCssClass(): string
    {
        return match($this) {
            self::INIT => 'secondary',
            self::WAIT => 'warning',
            self::PROCESS => 'info',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
            self::REJECTED => 'dark',
            self::ERROR => 'danger',
            self::REFUNDED => 'secondary',
            self::CANCELLED => 'secondary',
        };
    }

    /**
     * Couleur hexadécimale pour le frontend
     */
    public function getColor(): string
    {
        return match($this) {
            self::INIT => '#6c757d',      // Gris
            self::WAIT => '#ffc107',      // Jaune/Orange
            self::PROCESS => '#17a2b8',   // Bleu clair
            self::COMPLETED => '#28a745', // Vert
            self::FAILED => '#dc3545',    // Rouge
            self::REJECTED => '#343a40',  // Gris foncé
            self::ERROR => '#dc3545',     // Rouge
            self::REFUNDED => '#6c757d',  // Gris
            self::CANCELLED => '#6c757d', // Gris
        };
    }

    /**
     * Icône (FontAwesome ou autre)
     */
    public function getIcon(): string
    {
        return match($this) {
            self::INIT => 'fa-hourglass-start',
            self::WAIT => 'fa-clock',
            self::PROCESS => 'fa-spinner fa-spin',
            self::COMPLETED => 'fa-check-circle',
            self::FAILED => 'fa-times-circle',
            self::REJECTED => 'fa-thumbs-down',
            self::ERROR => 'fa-exclamation-triangle',
            self::REFUNDED => 'fa-undo',
            self::CANCELLED => 'fa-ban',
        };
    }

    /**
     * Description du statut
     */
    public function getDescription(): string
    {
        return match($this) {
            self::INIT => 'Le paiement a été créé mais pas encore envoyé au processeur',
            self::WAIT => 'En attente de confirmation du paiement par l\'utilisateur',
            self::PROCESS => 'Le paiement est en cours de traitement',
            self::COMPLETED => 'Le paiement a été effectué avec succès',
            self::FAILED => 'Le paiement a échoué (erreur technique ou timeout)',
            self::REJECTED => 'Le paiement a été rejeté manuellement par un administrateur',
            self::ERROR => 'Une erreur technique s\'est produite',
            self::REFUNDED => 'Le paiement a été remboursé',
            self::CANCELLED => 'Le paiement a été annulé par l\'utilisateur',
        };
    }

    /**
     * Indique si le paiement est dans un état final
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::REJECTED, self::ERROR, self::REFUNDED, self::CANCELLED]);
    }

    /**
     * Indique si le paiement est réussi
     */
    public function isSuccess(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Retourne toutes les infos du statut pour l'API
     */
    public function toArray(): array
    {
        return [
            'code' => $this->name,
            'label' => $this->getLabel(),
            'labelEn' => $this->getLabelEn(),
            'color' => $this->getColor(),
            'cssClass' => $this->getCssClass(),
            'icon' => $this->getIcon(),
            'description' => $this->getDescription(),
            'isFinal' => $this->isFinal(),
            'isSuccess' => $this->isSuccess(),
        ];
    }

    /**
     * Conversion souple depuis une string (labels FR/EN, alias, casse, espaces)
     */
    public static function fromString(string $value): self
    {
        $v = strtolower(trim($value));
        return match ($v) {
            'initialisation', 'init', 'initialization' => self::INIT,
            'attente', 'attente de paiement', 'wait', 'pending', 'en attente' => self::WAIT,
            'attente de traitement', 'process', 'processing', 'en traitement', 'en cours' => self::PROCESS,
            'approuve', 'approuvé', 'traite et approuve', 'traite et approuvé', 'approved', 'approve', 'complété', 'completed', 'success' => self::COMPLETED,
            'failed', 'echec', 'échoué' => self::FAILED,
            'rejeté', 'rejected', 'reject', 'refused', 'refuse', 'desapprouve', 'désapprouvé', 'traite et refuse', 'traite et refusé' => self::REJECTED,
            'erreur', 'error' => self::ERROR,
            'remboursé', 'refunded', 'refund' => self::REFUNDED,
            'annulé', 'cancelled', 'canceled', 'cancel' => self::CANCELLED,
            default => self::WAIT,
        };
    }

    /**
     * Conversion depuis code FlexPay
     */
    public static function fromFlexPayCode(string $code): self
    {
        return match ($code) {
            '0' => self::COMPLETED,  // Succès
            '1' => self::FAILED,     // Échec
            '2' => self::PROCESS,    // En cours
            default => self::ERROR,
        };
    }

    /**
     * Pour formulaires: [label => value]
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }
        return $choices;
    }

    /**
     * Retourne tous les statuts avec leurs infos
     */
    public static function getAllStatuses(): array
    {
        return array_map(fn($case) => $case->toArray(), self::cases());
    }
}

