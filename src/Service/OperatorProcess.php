<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Exception\PaymentException;
use App\Service\Gateway\GatewayInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class OperatorProcess
{
    public Transaction $transaction;

    /** @var GatewayInterface[] $gateways */
    public array $gateways;

    public function __construct(
        #[TaggedIterator('app.payment_gateway')]
        iterable $gateways
    )
    {
        $this->gateways = is_array($gateways) ? $gateways : iterator_to_array($gateways);
    }

    /**
     * @throws PaymentException
     */
    public function process(Transaction $transaction): mixed
    {
        foreach ($this->gateways as $gateway) {
            if (in_array($transaction->getOperator(), $gateway->support()))
                return $gateway->process($transaction);
        }

        throw new PaymentException("La méthode de paiement n'existe pas");
    }

    /**
     * @throws \Exception
     */
    public function check(Transaction $transaction): mixed
    {
        foreach ($this->gateways as $gateway) {
            if (in_array($transaction->getOperator(), $gateway->support()))
                return $gateway->check($transaction);
        }

        throw new \Exception("La méthode de paiement n'existe pas");
    }
}
