<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\Course\ApiCreateTransactionController;
use App\Controller\Api\Transaction\ApiTransactionNewController;
use Doctrine\Common\Collections\Collection;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: "/transaction/create",
            controller: ApiCreateTransactionController::class,
            openapiContext: ['summary' => self::DESCRIPTION],
            description: self::DESCRIPTION,
        )
    ]
)]
class CreateTransactionRessouce
{
    public const DESCRIPTION = "Crée une nouvelle transaction d'achat de cours ou d'une commande";

    public string $phone;
    public ?int $order_id = 0;
    public array $packages = [];
    public array $courses = [];
    public array $teachers = [];

    public string $currency;

}
