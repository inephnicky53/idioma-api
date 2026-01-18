<?php

namespace App\State;

use App\Entity\User;
use App\Entity\Subscription;
use App\Entity\SubscriptionPlan;
use App\Entity\Payment;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Enum\Currency;
use App\Service\Payment\PaymentManager;
use App\Service\RateService;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use DateTime;

readonly class UserRegisterProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface      $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface    $jwtManager,
        private PaymentManager              $paymentManager,
        private RateService                 $rateService,
        private LoggerInterface             $logger,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof User) {
            return $data;
        }

        // Vérifier si c'est une inscription (POST sur /auth/register)
        if ($operation->getName() !== 'register') {
            return $data;
        }

        // Vérifier si l'email existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data->getEmail()]);
        if ($existingUser) {
            throw new Exception('Cet email est déjà utilisé');
        }

        // Hacher le mot de passe
        $plainPassword = $data->getPassword();
        if (!$plainPassword) {
            throw new Exception('Password is required');
        }

        $hashedPassword = $this->passwordHasher->hashPassword($data, $plainPassword);
        $data->setPassword($hashedPassword);

        // Définir les valeurs par défaut
        $data->setIsActive(true);
        $data->setCreatedAt(new DateTime());

        // Démarrer une transaction
        $this->entityManager->beginTransaction();

        try {
            // Sauvegarder l'utilisateur
            $this->entityManager->persist($data);
            $this->entityManager->flush();

            // Vérifier si c'est une inscription avec paiement (subscriptionPlanId fourni)
            $subscriptionPlanId = $data->getSubscriptionPlanId();
            $paymentMethod = $data->getPaymentMethod();

            if ($subscriptionPlanId && $paymentMethod) {
                // Vérifier que le plan existe et est actif
                $plan = $this->entityManager->getRepository(SubscriptionPlan::class)->find($subscriptionPlanId);
                if (!$plan || !$plan->isActive()) {
                    throw new Exception('Plan d\'abonnement invalide ou inactif');
                }

                // Créer le paiement
                $payment = $this->createPayment($data, $plan, $paymentMethod);

                // Traiter le paiement selon la méthode
                $this->processPayment($payment, $paymentMethod);

                $this->logger->info('User registered with payment', [
                    'userId' => $data->getId(),
                    'paymentId' => $payment->getId(),
                    'paymentMethod' => $paymentMethod->value
                ]);
            } else {
                // Inscription simple au club si les champs club sont remplis
                if ($data->isClubMember() || $data->getLevel()) {
                    $this->createClubSubscription($data);
                }
            }

            // Commit la transaction
            $this->entityManager->commit();

            // Générer le JWT token
            $token = $this->jwtManager->create($data);
            $data->setToken($token);

            return $data;

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Registration failed', [
                'error' => $e->getMessage(),
                'email' => $data->getEmail() ?? 'unknown'
            ]);

            throw $e;
        }
    }

    private function createPayment(User $user, SubscriptionPlan $plan, PaymentMethod $paymentMethod): Payment
    {
        $payment = new Payment();
        $payment->setUser($user);
        $payment->setSubscriptionPlan($plan);
        $payment->setPaymentMethod($paymentMethod);
        $payment->setTransactionId(strtoupper(uniqid('PAY_')));
        $payment->setStatus(PaymentStatus::INIT);
        $payment->setIsSmsSend(false);

        // Gérer la devise et le montant
        $currency = $user->getCurrency() ?? $plan->getCurrency();

        if ($currency !== $plan->getCurrency()) {
            $amount = $this->rateService->convert(
                (float) $plan->getPrice(),
                $plan->getCurrency(),
                $currency
            );
            $payment->setAmount((string) round($amount, 2));
        } else {
            $payment->setAmount($plan->getPrice());
        }
        $payment->setCurrency($currency);

        // Formater le téléphone si présent
        if (!empty($user->getPhone())) {
            $phone = PaymentMethod::formatPhoneNumber($user->getPhone());
            $payment->setPhone($phone);
        }

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    private function processPayment(Payment $payment, PaymentMethod $paymentMethod): void
    {
        if ($paymentMethod !== PaymentMethod::CASH) {
            // Initier le paiement mobile
            $this->paymentManager->process($payment, [
                'operator' => $paymentMethod,
                'phone' => $payment->getPhone()
            ]);
        } else {
            // Pour CASH, mettre en attente
            $payment->setStatus(PaymentStatus::WAIT);
            $this->entityManager->persist($payment);
            $this->entityManager->flush();
        }
    }

    private function createClubSubscription(User $user): void
    {
        // Récupérer ou créer le plan "Club Plan"
        $clubPlan = $this->entityManager->getRepository(SubscriptionPlan::class)->findOneBy(['type' => 'club']);
        if (!$clubPlan) {
            $clubPlan = new SubscriptionPlan();
            $clubPlan->setName('Club Plan');
            $clubPlan->setDescription('Plan Club');
            $clubPlan->setPrice(50.00);
            $clubPlan->setDurationDays(30);
            $clubPlan->setType('club');
            $clubPlan->setSessionsLimit(4);
            $clubPlan->setIsActive(true);
            $this->entityManager->persist($clubPlan);
            $this->entityManager->flush();
        }

        // Créer la subscription
        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setPlan($clubPlan);
        $subscription->setStartDate(new DateTime());
        $subscription->setEndDate((new DateTime())->modify('+30 days'));
        $subscription->setStatus('active');
        $subscription->setSessionsUsed(0);
        $subscription->setAutoRenew(true);

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        // Marquer l'utilisateur comme membre du club
        $user->setIsClubMember(true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
