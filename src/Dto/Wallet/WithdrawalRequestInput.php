<?php

namespace App\Dto\Wallet;

use App\Entity\Currency;
use App\Entity\Payment;
use Symfony\Component\Validator\Constraints as Assert;

final class WithdrawalRequestInput
{
    #[Assert\NotNull]
    #[Assert\Positive(message: "The withdrawal amount must be positive.")]
    public float $amount;

    #[Assert\NotNull(message: "You must specify a currency.")]
    public Currency $currency;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [Payment::class, 'getMethodList'])]
    public string $method;

    #[Assert\NotBlank(message: "Payment details (e.g., PayPal email, bank account) are required.")]
    public string $methodData;
}