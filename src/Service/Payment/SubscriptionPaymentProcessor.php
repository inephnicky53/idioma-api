<?php

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\SubscriptionPlan;
use App\Entity\User;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\SubscriptionType;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Exception\PaymentException;
use Doctrine\ORM\EntityManagerInterface;
use DateTime;

class SubscriptionPaymentProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly OperatorProcess $operatorProcess,
    ) {}

    /**
     * Traiter le paiement d'un abonnement au club
     *
     * @throws PaymentException
     */
    public function processClubSubscription(
        User $user,
        SubscriptionPlan $plan,
        PaymentMethod $paymentMethod,
        ?string $phone = null
    ): Payment {
        // Valider le plan
        if ($plan->getType() !== SubscriptionType::CLUB->value && 
            $plan->getType() !== SubscriptionType::BOTH->value) {
            throw new PaymentException('Ce plan n\'inclut pas l\'accès au club');
        }

        // Créer le paiement
        $payment = new Payment();
        $payment->setUser($user);
        $payment->setSubscriptionPlan($plan);
        $payment->setAmount($plan->getPrice());
        $payment->setPaymentMethod($paymentMethod->value);
        $payment->setStatus('pending');
        $payment->setPaidAt(new DateTime());

        // Configurer les options du processeur
        $this->operatorProcess->setOptions([
            'operator' => $paymentMethod,
            'phone' => $phone,
            'demande_type' => TransactionType::CLUB_MEMBERSHIP,
            'payment_type' => PaymentType::CLUB_MEMBERSHIP,
        ]);

        // Créer la transaction
        try {
            $transaction = $this->operatorProcess->createTransaction(
                TransactionType::CLUB_MEMBERSHIP,
                $payment
            );

            $payment->setTransactionId($transaction->getId());
            $this->manager->persist($payment);
            $this->manager->flush();

            return $payment;
        } catch (PaymentException $e) {
            $payment->setStatus('failed');
            $this->manager->persist($payment);
            $this->manager->flush();
            throw $e;
        }
    }

    /**
     * Créer une souscription après paiement approuvé
     */
    public function createSubscriptionFromPayment(Payment $payment): Subscription
    {
        if ($payment->getStatus() !== 'completed') {
            throw new PaymentException('Le paiement n\'est pas complété');
        }

        $subscription = new Subscription();
        $subscription->setUser($payment->getUser());
        $subscription->setPlan($payment->getSubscriptionPlan());
        $subscription->setStartDate(new DateTime());
        
        $endDate = new DateTime();
        $endDate->modify('+' . $payment->getSubscriptionPlan()->getDurationDays() . ' days');
        $subscription->setEndDate($endDate);
        
        $subscription->setStatus('active');
        $subscription->setAutoRenew(false);

        $this->manager->persist($subscription);
        $this->manager->flush();

        return $subscription;
    }

    /**
     * Marquer le paiement comme complété
     */
    public function completePayment(Payment $payment): void
    {
        $payment->setStatus('completed');
        $this->manager->persist($payment);
        $this->manager->flush();
    }

    /**
     * Marquer le paiement comme échoué
     */
    public function failPayment(Payment $payment, string $reason = ''): void
    {
        $payment->setStatus('failed');
        if ($reason) {
            $payment->setNotes($reason);
        }
        $this->manager->persist($payment);
        $this->manager->flush();
    }
}

