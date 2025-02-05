<?php

namespace App\State\Payment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\CreatePaymentInput;
use App\Exception\PaymentException;
use App\Service\Payment\PaymentManager;

class InitiatePaymentProcessor implements ProcessorInterface
{
    public function __construct(private readonly PaymentManager $manager)
    {
    }

    /**
     * @throws PaymentException
     * @var CreatePaymentInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        return $this->manager->initiate($data);
    }
}
