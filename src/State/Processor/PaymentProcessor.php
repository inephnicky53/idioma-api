<?php

namespace App\State\Processor;

use App\Dto\CreatePaymentDto;
use App\Entity\Payment;
use App\Exception\PaymentException;
use App\Service\Payment\PaymentManager;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use InvalidArgumentException;

readonly class PaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private PaymentManager $paymentManager,
    ) {}

    /**
     * @throws PaymentException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Payment
    {
        if (!$data instanceof CreatePaymentDto) {
            throw new InvalidArgumentException('Expected CreatePaymentInput');
        }


        // Déléguer la création au Manager
        return $this->paymentManager->createFromInput($data);
    }
}

