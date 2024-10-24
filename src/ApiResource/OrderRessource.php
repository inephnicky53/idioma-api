<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\CreateOrderInput;
use App\State\Transaction\CreateTransactionProcessor;

#[ApiResource(
    shortName: 'Order',
    operations: [
        new Post(
            uriTemplate: '/orders',
            security: 'is_granted("ROLE_USER")',
            input: CreateOrderInput::class,
            processor: CreateTransactionProcessor::class
        )
    ]
)]
final class OrderRessource
{
}
