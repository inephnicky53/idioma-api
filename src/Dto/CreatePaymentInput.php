<?php

namespace App\Dto;

use App\Entity\Currency;
use Symfony\Component\Validator\Constraints as Assert;

class CreatePaymentInput
{
    public ?float $amount = null;

    public ?string $method = null;
    public ?string $methodData = null;

    public ?string $type = null;

    #[Assert\NotBlank]
    #[Assert\NotNull]
    public ?Currency $currency = null;
}