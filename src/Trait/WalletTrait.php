<?php

namespace App\Trait;

use App\Kimia;
use Doctrine\ORM\Mapping as ORM;

trait WalletTrait
{
    #[ORM\Column(nullable: true)]
    private ?array $wallets = [];
    
    public function getWallets(): array
    {
        return $this->wallets ?? [];
    }

    public function createWallet(string $currency): self
    {
        if (!array_key_exists($currency, $this->getWallets())) {
            $this->wallets[$currency] = 0;
        }

        return $this;
    }

    public function removeWallet(string $currency): self
    {
        if (array_key_exists($currency, $this->getWallets())) {
            unset($this->wallets[$currency]);
        }

        return $this;
    }

    public function addToWallet(float $amount, string $currency = Kimia::CURRENCY_DEFAULT): self
    {
        $this->createWallet($currency);
        $this->wallets[$currency] += $amount;

        return $this;
    }

    public function debitToWallet(float $amount, string $currency = Kimia::CURRENCY_DEFAULT): self
    {
        $this->wallets[$currency] -= $amount;

        return $this;
    }

    public function supportWallets(array $currencies = Kimia::CURRENCY_SUPPORTS): self
    {
        array_map(function ($currency) {
            $this->createWallet($currency);
        }, $currencies);

        return $this;
    }
}