<?php

namespace App\State\Transaction;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Service\Transaction\TransactionManager;

class CheckTransactionProvider implements ProviderInterface
{
    public function __construct(private readonly TransactionManager $manager)
    {
    }

    /**
     * @throws \Exception
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->manager->check($uriVariables['id']);
    }
}
