<?php

namespace App\Model\Wallet;

use App\Kimia;

interface WalletInterface
{
    public function getWallets(): array;
    public function createWallet(string $currency): self;
    public function removeWallet(string $currency): self;
    public function addToWallet(float $amount, string $currency = Kimia::CURRENCY_DEFAULT): self;
    public function debitToWallet(float $amount, string $currency = Kimia::CURRENCY_DEFAULT): self;
    public function supportWallets(array $currencies = Kimia::CURRENCY_SUPPORTS): self;
    public function addWallet(Wallet $wallet);
    public function findWallet(string $currency): Wallet|null;
    public function getBalance(string $currency): float|null;
    public function setWallets(array $wallets);
}