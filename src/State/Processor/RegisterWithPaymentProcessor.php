<?php

namespace App\State\Processor;

use App\Dto\RegisterWithPaymentDto;
use App\Entity\Payment;
use App\Entity\SubscriptionPlan;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Service\Payment\PaymentManager;
use App\Service\RateService;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Processor pour l'inscription avec paiement en une seule transaction
 */
readonly class RegisterWithPaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private PaymentManager $paymentManager,
        private RateService $rateService,
        private LoggerInterface $logger,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        dd($data);
        if (!$data instanceof RegisterWithPaymentDto) {
            throw new InvalidArgumentException('Expected RegisterWithPaymentDto');
        }

        // Vérifier si l'email existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $data->email]);

        if ($existingUser) {
            throw new ConflictHttpException('Cet email est déjà utilisé');
        }

        // Vérifier que le plan existe et est actif
        $plan = $this->entityManager->getRepository(SubscriptionPlan::class)
            ->find($data->subscriptionPlanId);

        if (!$plan || !$plan->isActive()) {
            throw new BadRequestHttpException('Plan d\'abonnement invalide ou inactif');
        }

        $paymentMethod = $data->getPaymentMethodEnum();

        // Démarrer une transaction
        $this->entityManager->beginTransaction();

        try {
            // 1. Créer l'utilisateur
            $user = $this->createUser($data);

            // 2. Créer le paiement
            $payment = $this->createPayment($user, $plan, $data, $paymentMethod);

            // 3. Traiter le paiement selon la méthode
            $this->processPayment($payment, $paymentMethod);

            // Commit la transaction
            $this->entityManager->commit();

            // 4. Générer le JWT token
            $token = $this->jwtManager->create($user);

            $this->logger->info('User registered with payment', [
                'userId' => $user->getId(),
                'paymentId' => $payment->getId(),
                'paymentMethod' => $paymentMethod->value
            ]);

            return $this->buildResponse($user, $payment, $plan, $token);

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Registration with payment failed', [
                'error' => $e->getMessage(),
                'email' => $data->email ?? 'unknown'
            ]);

            throw $e;
        }
    }

    private function createUser(RegisterWithPaymentDto $data): User
    {
        $user = new User();
        $user->setEmail($data->email);
        $user->setFirstName($data->firstName);
        $user->setLastName($data->lastName);
        $user->setPhone($data->phone);
        $user->setIsActive(true);
        $user->setCreatedAt(new DateTime());

        $hashedPassword = $this->passwordHasher->hashPassword($user, $data->password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createPayment(User $user, SubscriptionPlan $plan, RegisterWithPaymentDto $data, PaymentMethod $paymentMethod): Payment
    {
        $payment = new Payment();
        $payment->setUser($user);
        $payment->setSubscriptionPlan($plan);
        $payment->setPaymentMethod($paymentMethod);
        $payment->setTransactionId(strtoupper(uniqid('PAY_')));
        $payment->setStatus(PaymentStatus::INIT);
        $payment->setIsSmsSend(false);

        // Gérer la devise et le montant
        $currency = $data->currency ?? $plan->getCurrency();

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
        if (!empty($data->phone)) {
            $phone = PaymentMethod::formatPhoneNumber($data->phone);
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

    private function buildResponse(User $user, Payment $payment, SubscriptionPlan $plan, string $token): array
    {
        return [
            'success' => true,
            'message' => 'Inscription réussie',
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'phone' => $user->getPhone(),
            ],
            'payment' => [
                'id' => $payment->getId(),
                'status' => $payment->getStatus()->value,
                'amount' => $payment->getAmount(),
                'currency' => $payment->getCurrency()->value,
                'transactionId' => $payment->getTransactionId(),
            ],
            'plan' => [
                'id' => $plan->getId(),
                'name' => $plan->getName(),
                'durationDays' => $plan->getDurationDays(),
                'sessionsLimit' => $plan->getSessionsLimit(),
            ]
        ];
    }
}

