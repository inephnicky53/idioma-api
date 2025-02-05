<?php

namespace App\Model\Wallet;

use App\Model\Initiable;
use App\Trait\ArrayableTrait;
use Symfony\Component\Serializer\Attribute\Groups;

class Wallet
{
    use Initiable;
    use ArrayableTrait;

    #[Groups(['teacher:wallet'])]
    private string $currency;

    #[Groups(['teacher:wallet'])]
    private float $balance;

    #[Groups(['teacher:wallet'])]
    private bool $isActive;

    /**
     * Constructeur pour initialiser le portefeuille.
     */
    public function __construct(string $currency, float $balance = 0, bool $isActive = true)
    {
        $this->currency = $currency;
        $this->balance = max(0, $balance); // Assure un solde initial non négatif.
        $this->isActive = $isActive;
    }

    /**
     * Retourne la devise du portefeuille.
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Retourne le solde du portefeuille.
     */
    public function getBalance(): float
    {
        return $this->balance;
    }

    /**
     * Définit un nouveau solde (positif uniquement).
     */
    public function setBalance(float $amount): self
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException("Balance cannot be negative.");
        }
        $this->balance = $amount;

        return $this;
    }

    /**
     * Ajoute un montant au solde.
     */
    public function credit(float $amount): self
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Credit amount must be positive.");
        }
        $this->balance += $amount;

        return $this;
    }

    /**
     * Débite un montant du solde.
     */
    public function debit(float $amount): self
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Debit amount must be positive.");
        }

        if ($this->balance < $amount) {
            throw new \InvalidArgumentException("Insufficient balance.");
        }

        $this->balance -= $amount;

        return $this;
    }

    /**
     * Vérifie si le portefeuille est actif.
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Active le portefeuille.
     */
    public function activate(): self
    {
        $this->isActive = true;

        return $this;
    }

    /**
     * Désactive le portefeuille.
     */
    public function deactivate(): self
    {
        $this->isActive = false;

        return $this;
    }

    /**
     * Retourne une représentation textuelle du portefeuille.
     */
    public function __toString(): string
    {
        return "{$this->balance} {$this->currency} (" . ($this->isActive ? "Active" : "Inactive") . ")";
    }
}
