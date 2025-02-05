<?php

namespace App\Service\Payment;

use App\Dto\CreatePaymentInput;
use App\Entity\Payment;
use App\Entity\User;
use App\Event\PaymentCreatedEvent;
use App\Event\PaymentDeclinedEvent;
use App\Event\PaymentValidatedEvent;
use App\Exception\PaymentException;
use App\Idioma;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class PaymentManager
{
    public function __construct(
        private EntityManagerInterface   $em,
        private Security                 $security,
        private EventDispatcherInterface $dispatcher
    )
    {
    }

    /**
     * @throws PaymentException
     */
    public function initiate(CreatePaymentInput $input): Payment
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $teacher = $user->getTeacher();

        if ($teacher->findWallet($input->currency->getMin())->getBalance() < $input->amount)
            throw new PaymentException('insufficient balance');

        $payment = (new Payment())
            ->setReference(uniqid("PY-$input->type-"))
            ->setAmount($input->amount)
            ->setCurrency($input->currency)
            ->setMethodData($input->methodData)
            ->setMethod($input->method)
            ->setType($input->type)
            ->setStatus(Idioma::STATUS_CREATED)
            ->setUser($user)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($payment);
        $this->em->flush();

        $this->dispatcher->dispatch(new PaymentCreatedEvent($payment));

        return $payment;
    }

    public function validate(Payment $payment): Payment
    {
        $user = $payment->getUser();
        $teacher = $user->getTeacher();
        $currency = $payment->getCurrency();

        $teacher->debitFromWallet($payment->getAmount(), $currency->getMin());

        ($payment)
            ->setStatus(Idioma::STATUS_SUCCESS)
            ->setPaidAt(new \DateTimeImmutable())
            ->setExecutedBy($this->security->getUser());

        $this->em->persist($payment);
        $this->em->flush();

        $this->dispatcher->dispatch(new PaymentValidatedEvent($payment));

        return $payment;
    }

    public function decline(Payment $payment): Payment
    {
        ($payment)
            ->setStatus(Idioma::STATUS_FAILED)
            ->setExecutedBy($this->security->getUser());

        $this->em->persist($payment);
        $this->em->flush();

        $this->dispatcher->dispatch(new PaymentDeclinedEvent($payment));

        return $payment;
    }
}
