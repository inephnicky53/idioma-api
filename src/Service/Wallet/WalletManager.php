<?php

namespace App\Service\Wallet;

use App\Dto\Wallet\WithdrawalRequestInput;
use App\Entity\Payment;
use App\Entity\Teacher;
use App\Event\PaymentCreatedEvent;
use App\Exception\InsufficientBalanceException;
use App\Idioma;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

readonly class WalletManager
{
    public function __construct(
        private EntityManagerInterface   $em,
        private EventDispatcherInterface $dispatcher
    )
    {
    }

    /**
     * @throws InsufficientBalanceException
     */
    public function requestWithdrawal(Teacher $teacher, WithdrawalRequestInput $input): Payment
    {
        $wallet = $teacher->findWallet($input->currency->getMin());

        if (!$wallet || $wallet->getBalance() < $input->amount) {
            throw new InsufficientBalanceException('Insufficient balance for this withdrawal request.');
        }

        $this->em->beginTransaction();
        try {
            $teacher->debitFromWallet($input->amount, $input->currency->getMin());

            $payment = (new Payment())
                ->setUser($teacher->getUser())
                ->setAmount($input->amount)
                ->setCurrency($input->currency)
                ->setMethod($input->method)
                ->setMethodData($input->methodData)
                ->setType(Payment::TYPE_DEMAND)
                ->setStatus(Idioma::STATUS_CREATED)
                ->setReference('WDR-' . uniqid());

            $this->em->persist($payment);
            $this->em->persist($teacher);
            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }

        $this->dispatcher->dispatch(new PaymentCreatedEvent($payment));

        return $payment;
    }
}