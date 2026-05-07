<?php

namespace App\Service\Payment;

use App\Entity\Payment;

interface PaymentProviderInterface
{
    public function createTransaction($candidate, int $type, array $options): Payment;
    public function checkTransaction(Payment $payment): array|bool;
    public function getName(): string;
}
