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
use App\Message\SendPaymentReceiptMessage;
use App\Service\RateService;
use App\Service\SmsService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class PaymentManager
{
    private PaymentProvider $defaultProvider;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RateService            $rateService,
        private Security               $security,
        private LoggerInterface        $logger,
        string                         $provider,
        private ?FlexPayProvider       $flexPayProvider,
        private SmsService             $smsService,
        private MessageBusInterface    $messageBus,
        private int                    $paymentWaitTimeout = 45,
        private int                    $paymentPollInterval = 3
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

        // Traiter le paiement selon la méthode (pas pour CASH)
        if ($paymentMethod !== PaymentMethod::CASH) {
            // Traitement synchrone auprès du provider (FlexPay).
            $this->process($payment, [
                'provider' => $this->defaultProvider,
                'operator' => $paymentMethod,
                'phone' => $phone,
            ]);

            // Mobile money : la confirmation se fait sur le téléphone de l'utilisateur.
            // On attend la résolution (polling FlexPay /check) jusqu'à un timeout, puis on
            // renvoie le statut final au front. La carte, elle, redirige (pas d'attente ici).
            if ($paymentMethod === PaymentMethod::MOBILE
                && $payment->getStatus() === PaymentStatus::WAIT) {
                $this->awaitResolution($payment);
            }
        } else {
            $payment->setStatus(PaymentStatus::WAIT);
            $this->entityManager->persist($payment);
            $this->entityManager->flush();
        }

        return $payment;
    }

    /**
     * Attend la résolution d'un paiement mobile money en interrogeant FlexPay (/check)
     * à intervalle régulier, jusqu'à un timeout. À la résolution réussie, le paiement
     * est complété et l'achat activé (idempotent vis-à-vis du callback grâce à paidAt).
     * En cas de timeout, le statut reste non final : le callback finalisera plus tard.
     */
    private function awaitResolution(Payment $payment): void
    {
        // S'assurer que la limite d'exécution PHP couvre l'attente (php.ini par défaut: 30s).
        // Ne couvre pas le timeout du serveur web : garder PAYMENT_WAIT_TIMEOUT en dessous.
        @set_time_limit($this->paymentWaitTimeout + 15);

        $deadline = time() + $this->paymentWaitTimeout;

        while (time() < $deadline) {
            sleep($this->paymentPollInterval);

            try {
                $this->check($payment);
            } catch (\Throwable $e) {
                $this->logger->warning('Payment wait: vérification FlexPay échouée', [
                    'paymentId' => $payment->getId(),
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($payment->getStatus()->isFinal()) {
                if ($payment->getStatus()->isSuccess() && $payment->getPaidAt() === null) {
                    $this->complete($payment);
                    $this->activatePurchase($payment);
                    $this->entityManager->flush();
                }
                return;
            }
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

        // Envoi du reçu en arrière-plan pour ne pas bloquer le callback / la requête courante
        $this->messageBus->dispatch(new SendPaymentReceiptMessage(
            paymentId: $payment->getId(),
        ));
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
