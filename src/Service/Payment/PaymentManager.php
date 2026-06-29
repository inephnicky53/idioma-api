<?php

namespace App\Service\Payment;

use App\Dto\CreatePaymentDto;
use App\Contract\PayableInterface;
use App\Entity\Course;
use App\Entity\CoursePurchase;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\SubscriptionPlan;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentMethod;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PurchaseType;
use App\Exception\PaymentException;
use App\Service\RateService;
use App\Service\EmailService;
use App\Service\SmsService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class PaymentManager
{
    /**
     * Durée maximale (secondes) d'attente synchrone de la résolution d'un
     * paiement mobile money avant de rendre la main au frontend.
     */
    private const WAIT_TIMEOUT_SECONDS = 90;

    /** Intervalle (secondes) entre deux vérifications du statut pendant l'attente. */
    private const WAIT_POLL_INTERVAL_SECONDS = 3;

    private PaymentProvider $defaultProvider;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RateService            $rateService,
        private Security               $security,
        private LoggerInterface        $logger,
        string                         $provider,
        private ?FlexPayProvider       $flexPayProvider = null,
        private EmailService           $emailService,
        private SmsService             $smsService
    ) {
        $this->defaultProvider = PaymentProvider::fromString($provider);
    }

    /**
     * Créer un paiement à partir du DTO
     * Les validations sont faites dans le DTO via Assert\Callback
     * @throws PaymentException
     */
    public function createFromInput(CreatePaymentDto $dto): Payment
    {
        // Vérifier que l'utilisateur est connecté
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour effectuer un paiement');
        }

        // Résoudre le produit à acheter (Course ou SubscriptionPlan)
        $payable = $this->resolvePayable($dto->purchaseType, $dto->purchaseId);
        if (!$payable) {
            throw new BadRequestHttpException('Produit introuvable');
        }

        if (!$payable->isAvailableForPurchase()) {
            throw new BadRequestHttpException('Ce produit n\'est pas disponible à l\'achat');
        }

        // Créer le paiement avec setPayable() qui configure automatiquement type, montant et devise
        $payment = new Payment();
        $payment->setUser($user);
        $payment->setPayable($payable);

        // Convertir le montant si la devise demandée est différente
        $requestedCurrency = $dto->currency ?? $payable->getCurrency();
        if ($requestedCurrency !== $payable->getCurrency()) {
            $amount = $this->rateService->convert(
                (float) $payable->getPrice(),
                $payable->getCurrency(),
                $requestedCurrency
            );
            $payment->setAmount((string) round($amount, 2));
            $payment->setCurrency($requestedCurrency);
        }

        // Convertir la méthode de paiement string en enum
        $paymentMethod = PaymentMethod::fromString($dto->paymentMethod);

        // Formater le numéro de téléphone si présent
        $phone = $dto->phone ? PaymentMethod::formatPhoneNumber($dto->phone) : null;

        $payment->setPaymentMethod($paymentMethod);
        $payment->setReference(strtoupper(uniqid('PAY_')));
        $payment->setStatus(PaymentStatus::INIT);
        $payment->setIsSmsSend(false);
        $payment->setPhone($phone);

        // Persister
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        // Paiement en espèces : aucun provider externe, on reste en attente
        // d'un encaissement manuel au bureau.
        if ($paymentMethod === PaymentMethod::CASH) {
            $payment->setStatus(PaymentStatus::WAIT);
            $this->entityManager->persist($payment);
            $this->entityManager->flush();
            return $payment;
        }

        // Ouvre la transaction chez le provider (open transaction).
        $this->process($payment, [
            'provider' => $this->defaultProvider,
            'operator' => $paymentMethod,
            'phone' => $phone
        ]);

        // Paiement par carte : l'utilisateur doit être redirigé vers la page de
        // paiement hébergée par FlexPay. On rend la main immédiatement pour que
        // le frontend effectue la redirection ; la confirmation arrivera ensuite
        // via le callback (puis la page de confirmation).
        if ($paymentMethod === PaymentMethod::CARD) {
            return $payment;
        }

        // Mobile money : on attend de façon synchrone la résolution de la
        // transaction afin de répondre directement au frontend (qui attend la
        // réponse) avec le statut final.
        $this->waitForResolution($payment);

        return $payment;
    }

    /**
     * Attend que le paiement atteigne un état final (succès / échec) puis rend
     * la main, de sorte que la requête POST /payments réponde directement avec
     * le résultat. Combine deux mécanismes :
     *  - la relecture en base, alimentée par le callback FlexPay qui s'exécute
     *    dans une autre requête HTTP ;
     *  - une vérification active auprès de FlexPay (filet de sécurité si le
     *    callback tarde ou n'arrive jamais, par ex. en local).
     *
     * NB: WAIT_TIMEOUT_SECONDS doit rester inférieur au timeout du reverse
     * proxy / serveur web pour éviter une coupure prématurée de la requête.
     */
    private function waitForResolution(Payment $payment): void
    {
        if ($payment->getStatus()->isFinal()) {
            $this->finalizeIfSuccessful($payment);
            return;
        }

        $deadline = time() + self::WAIT_TIMEOUT_SECONDS;

        while (time() < $deadline) {
            sleep(self::WAIT_POLL_INTERVAL_SECONDS);

            // 1) Relecture en base : capte la mise à jour faite par le callback.
            try {
                $this->entityManager->refresh($payment);
            } catch (\Throwable $e) {
                $this->logger->warning('Payment refresh during wait failed', [
                    'paymentId' => $payment->getId(),
                    'error' => $e->getMessage(),
                ]);
            }

            if ($payment->getStatus()->isFinal()) {
                break;
            }

            // 2) Vérification active auprès de FlexPay (au cas où le callback tarde).
            try {
                $this->check($payment);
            } catch (\Throwable $e) {
                $this->logger->warning('Payment check during wait failed', [
                    'paymentId' => $payment->getId(),
                    'error' => $e->getMessage(),
                ]);
            }

            if ($payment->getStatus()->isFinal()) {
                break;
            }
        }

        $this->finalizeIfSuccessful($payment);
    }

    /**
     * Marque le paiement comme complété et active l'achat s'il a réussi et que
     * cela n'a pas déjà été fait par le callback. Idempotent grâce à `paidAt`
     * (positionné par complete()).
     */
    private function finalizeIfSuccessful(Payment $payment): void
    {
        if ($payment->getStatus()->isSuccess() && $payment->getPaidAt() === null) {
            $this->complete($payment);
            $this->activatePurchase($payment);
            $this->entityManager->flush();
        }
    }

    /**
     * Traiter un paiement via un provider externe
     */
    public function process(Payment $payment, array $options): Payment
    {
        $provider = $options['provider'] ?? $this->defaultProvider;

        // Convertir en enum si c'est une string
        if (is_string($provider)) {
            $provider = PaymentProvider::fromString($provider);
        }

        $providerService = $this->getProvider($provider);

        // Convertir la string en enum si nécessaire
        $operator = $options['operator'];
        if (is_string($operator)) {
            $operator = PaymentMethod::fromString($operator);
        }

        $type = match ($operator) {
            PaymentMethod::MOBILE => 1,
            PaymentMethod::CARD => 2,
            default => throw new PaymentException("Méthode de paiement non supportée: " . $operator->value)
        };

        return $providerService->createTransaction($payment, $type, $options);
    }


    /**
     * Résoudre le produit à acheter depuis le type et l'ID
     */
    private function resolvePayable(PurchaseType $type, int $id): ?PayableInterface
    {
        return match ($type) {
            PurchaseType::COURSE => $this->entityManager->getRepository(Course::class)->find($id),
            PurchaseType::SUBSCRIPTION_CLUB => $this->entityManager->getRepository(SubscriptionPlan::class)->find($id),
        };
    }

    /**
     * Activer l'achat après paiement réussi (abonnement ou cours)
     */
    public function activatePurchase(Payment $payment): void
    {
        $purchaseType = $payment->getPurchaseType();

        match ($purchaseType) {
            PurchaseType::SUBSCRIPTION_CLUB => $this->activateSubscription($payment),
            PurchaseType::COURSE => $this->activateCoursePurchase($payment),
        };
    }

    /**
     * Activer l'abonnement après paiement réussi
     */
    public function activateSubscription(Payment $payment): void
    {
        $user = $payment->getUser();
        $plan = $payment->getSubscriptionPlan();

        if (!$user || !$plan) {
            $this->logger->error('Cannot activate subscription: missing user or plan', [
                'paymentId' => $payment->getId()
            ]);
            return;
        }

        // Vérifier si l'utilisateur a déjà un abonnement actif pour ce plan
        $existingSubscription = $this->entityManager->getRepository(Subscription::class)
            ->findOneBy(['user' => $user, 'plan' => $plan, 'status' => 'active']);

        if ($existingSubscription) {
            // Prolonger l'abonnement existant
            /** @var DateTime $currentEndDate */
            $currentEndDate = $existingSubscription->getEndDate();
            $newEndDate = (clone $currentEndDate)->modify('+' . $plan->getDurationDays() . ' days');
            $existingSubscription->setEndDate($newEndDate);

            $this->logger->info('Subscription extended', [
                'subscriptionId' => $existingSubscription->getId(),
                'newEndDate' => $newEndDate->format('Y-m-d')
            ]);
        } else {
            // Créer un nouvel abonnement
            $subscription = new Subscription();
            $subscription->setUser($user);
            $subscription->setPlan($plan);
            $subscription->setStartDate(new DateTime());

            $endDate = new DateTime();
            $endDate->modify('+' . $plan->getDurationDays() . ' days');
            $subscription->setEndDate($endDate);

            $subscription->setStatus('active');
            $subscription->setAutoRenew(false);
            $subscription->setSessionsUsed(0);

            $this->entityManager->persist($subscription);

            // Marquer l'utilisateur comme membre du club si c'est un plan club
            if ($plan->getType() === 'club') {
                $user->setIsClubMember(true);
            }

            $this->logger->info('Subscription created', [
                'userId' => $user->getId(),
                'planId' => $plan->getId(),
                'endDate' => $endDate->format('Y-m-d')
            ]);
        }
    }

    /**
     * Activer l'achat de cours après paiement réussi
     */
    public function activateCoursePurchase(Payment $payment): void
    {
        $user = $payment->getUser();
        $course = $payment->getCourse();

        if (!$user || !$course) {
            $this->logger->error('Cannot activate course purchase: missing user or course', [
                'paymentId' => $payment->getId()
            ]);
            return;
        }

        // Vérifier si l'achat existe déjà
        $existingPurchase = $this->entityManager->getRepository(CoursePurchase::class)
            ->findOneBy(['user' => $user, 'course' => $course]);

        if ($existingPurchase) {
            // Réactiver si désactivé
            $existingPurchase->setIsActive(true);
            $this->logger->info('Course purchase reactivated', [
                'purchaseId' => $existingPurchase->getId()
            ]);
        } else {
            // Créer un nouvel achat
            $purchase = new CoursePurchase();
            $purchase->setUser($user);
            $purchase->setCourse($course);
            $purchase->setPayment($payment);
            $this->entityManager->persist($purchase);

            $this->logger->info('Course purchase created', [
                'userId' => $user->getId(),
                'courseId' => $course->getId()
            ]);
        }
    }

    /**
     * Marquer un paiement comme complété
     */
    public function complete(Payment $payment): void
    {
        $payment->setStatus(PaymentStatus::COMPLETED);
        $payment->setPaidAt(new DateTime());
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        // Send payment receipt notifications (email only for now)
        $this->emailService->sendPaymentReceiptEmail($payment);
    }

    /**
     * Marquer un paiement comme échoué
     */
    public function fail(Payment $payment, ?string $reason = null): void
    {
        $payment->setStatus(PaymentStatus::FAILED);
        if ($reason) {
            $payment->setNotes($reason);
        }
        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }

    /**
     * Vérifier le statut d'un paiement
     * @throws PaymentException
     */
    public function check(Payment $payment): bool|array
    {
        $providerService = $this->getProvider($this->defaultProvider);
        return $providerService->checkTransaction($payment);
    }

    /**
     * Récupérer le service provider selon l'enum
     * @throws PaymentException
     */
    private function getProvider(PaymentProvider $provider): PaymentProviderInterface
    {
        return match ($provider) {
            PaymentProvider::FLEXPAY => $this->flexPayProvider ?? throw new PaymentException('FlexPay provider non configuré'),
            PaymentProvider::STRIPE => throw new PaymentException('Stripe provider non implémenté'),
            PaymentProvider::PAYPAL => throw new PaymentException('PayPal provider non implémenté'),
        };
    }
}
