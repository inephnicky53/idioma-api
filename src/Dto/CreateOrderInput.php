<?php

namespace App\Dto;

use App\Entity\Currency;
use App\Entity\Transaction;
use App\Model\OrderProductModel;
use Symfony\Component\Validator\Constraints as Assert;

class CreateOrderInput
{
    public ?string $phone = null;

    #[Assert\Choice(callback: [Transaction::class, 'getOperatorList'])]
    public ?string $operator = null;

    /** @var OrderProductModel[] $products */
    public array $products = [];

    #[Assert\NotBlank]
    #[Assert\NotNull]
    public ?Currency $currency = null;
}