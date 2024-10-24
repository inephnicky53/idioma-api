<?php

namespace App\State\Transaction;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\CreateOrderInput;
use App\Exception\PaymentException;
use App\Service\Transaction\TransactionManager;

class CreateTransactionProcessor implements ProcessorInterface
{
    public function __construct(private readonly TransactionManager $manager)
    {
    }

    /**
     * @throws PaymentException
     * @var CreateOrderInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        return $this->manager->create($data);
    }
}
