<?php

namespace App\Trait;

use App\Model\Wallet\Wallet;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

trait WalletTrait
{
    #[ORM\Column(nullable: true)]
    #[Groups(['teacher:wallet'])]
    private ?array $wallets = [];

    /**
     * Get all wallets as an array.
     */
    public function getWallets(): array
    {
        return $this->wallets ?? [];
    }

    /**
     * Set wallets directly (replaces all wallets).
     */
    public function setWallets(array $wallets): static
    {
        $this->wallets = $wallets;

        return $this;
    }

    /**
     * Add a Wallet instance to the wallets array.
     */
    public function addWallet(Wallet $wallet): static
    {
        if (!isset($this->wallets[$wallet->getCurrency()])) {
            $this->wallets[$wallet->getCurrency()] = $wallet->toArray();
        }

        return $this;
    }

    /**
     * Create a wallet for a specific currency if it doesn't exist.
     */
    public function createWallet(string $currency): static
    {
        if (!isset($this->wallets[$currency])) {
            $this->wallets[$currency] = ['balance' => 0];
        }

        return $this;
    }

    /**
     * Remove a wallet for a specific currency.
     */
    public function removeWallet(string $currency): static
    {
        if (isset($this->wallets[$currency])) {
            unset($this->wallets[$currency]);
        }

        return $this;
    }

    /**
     * Add an amount to a wallet's balance for a specific currency.
     */
    public function addToWallet(float $amount, string $currency): static
    {
        $this->createWallet($currency);
        $this->wallets[$currency]['balance'] += $amount;

        return $this;
    }

    /**
     * Debit an amount from a wallet's balance for a specific currency.
     */
    public function debitFromWallet(float $amount, string $currency): static
    {
        $this->createWallet($currency);
        if ($this->wallets[$currency]['balance'] >= $amount) {
            $this->wallets[$currency]['balance'] -= $amount;
        } else {
            throw new \InvalidArgumentException("Insufficient balance in the wallet for currency: $currency.");
        }

        return $this;
    }

    /**
     * Support multiple currencies by ensuring wallets exist for each.
     */
    public function supportWallets(array $currencies): static
    {
        foreach ($currencies as $currency) {
            $this->createWallet($currency);
        }

        return $this;
    }

    /**
     * Find a Wallet instance by currency.
     */
    public function findWallet(string $currency): ?Wallet
    {
        return isset($this->wallets[$currency])
            ? new Wallet($currency, $this->wallets[$currency]['balance'])
            : null;
    }

    /**
     * Get the balance of a specific wallet.
     */
    public function getBalance(string $currency): ?float
    {
        return $this->wallets[$currency]['balance'] ?? null;
    }
}
