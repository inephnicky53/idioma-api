<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\RegisterDto;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\SubscriptionPlan;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Service\Payment\PaymentManager;
use App\Service\RateService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class UserRegisterProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface      $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface    $jwtManager,
        private PaymentManager              $paymentManager,
        private RateService                 $rateService,
        private LoggerInterface             $logger,
        private \App\Service\EmailService   $emailService,
        private \App\Service\SmsService     $smsService,
    ) {}

    /**
     * @param RegisterDto $data
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return mixed
     * @throws Exception
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Vérifier si c'est une inscription (POST sur /auth/register)
        if ($operation->getName() !== 'register') {
            return $data;
        }

        // Si les données ne sont pas un RegisterDto, essayer de les désérialiser manuellement
        /*if (!$data instanceof RegisterDto) {
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $jsonData = json_decode($request->getContent(), true);
                if (is_array($jsonData)) {
                    // Convertir la devise en enum si fournie
                    $currency = null;
                    if (isset($jsonData['currency']) && is_string($jsonData['currency'])) {
                        try {
                            $currency = Currency::from($jsonData['currency']);
                        } catch (\ValueError $e) {
                            // Ignorer les devises invalides
                        }
                    }

                    // Créer le DTO avec les données
                    $data = new RegisterDto(
                        email: $jsonData['email'] ?? null,
                        password: $jsonData['password'] ?? null,
                        firstName: $jsonData['firstName'] ?? null,
                        lastName: $jsonData['lastName'] ?? null,
                        phone: $jsonData['phone'] ?? null,
                        phonePayment: $jsonData['phonePayment'] ?? null,
                        level: $jsonData['level'] ?? null,
                        participationType: $jsonData['participationType'] ?? null,
                        subscriptionPlanId: isset($jsonData['subscriptionPlanId']) ? (int)$jsonData['subscriptionPlanId'] : null,
                        paymentMethod: $jsonData['paymentMethod'] ?? null,
                        currency: $currency,
                    );
                }
            }
        }*/

        if (!$data instanceof RegisterDto) {
            $this->logger->warning('Data is not RegisterDto', ['type' => get_class($data)]);
            throw new \Exception('Invalid data type');
        }

        $this->logger->info('UserRegisterProcessor.process called', [
            'dataType' => get_class($data),
            'operationName' => $operation->getName(),
            'data' => [
                'email' => $data->email,
                'firstName' => $data->firstName,
                'lastName' => $data->lastName,
            ]
        ]);

        // Vérifier si l'email existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data->email]);
        if ($existingUser) {
            throw new ConflictHttpException('Cet email est déjà utilisé');
        }

        // Vérifier si le téléphone existe déjà (s'il est fourni)
        if (!empty($data->phone)) {
            $existingPhone = $this->entityManager->getRepository(User::class)->findOneBy(['phone' => $data->phone]);
            if ($existingPhone) {
                throw new ConflictHttpException('Ce numéro de téléphone est déjà utilisé');
            }
        }

        // Démarrer une transaction
        $this->entityManager->beginTransaction();

        try {
            // Créer l'utilisateur
            $user = $this->createUser($data);

            // Générer et envoyer OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = (new DateTime())->modify('+10 minutes');
            $user->setPhoneOtp($otp);
            $user->setPhoneOtpExpiresAt($expiresAt);
            $this->entityManager->persist($user);

            // Envoyer OTP par email
            $this->emailService->sendOtpEmail($user, $otp);

            // Envoyer OTP par SMS si téléphone disponible
            if ($user->getPhone()) {
                $this->smsService->sendOtpSms($user, $otp);
            }

            // Inscription simple (pas de paiement)
            if ($data->level || $data->participationType) {
                $this->createClubSubscription($user, $data);
            }

            // Commit la transaction
            $this->entityManager->commit();

            // Générer le JWT token et le stocker dans l'utilisateur pour la sérialisation
            $token = $this->jwtManager->create($user);

            // Ajouter le token à l'utilisateur pour la réponse
            $user->setJwtToken($token);

            return $user;

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Registration failed', [
                'error' => $e->getMessage(),
                'email' => $data->email ?? 'unknown'
            ]);

            throw $e;
        }
    }

    private function createUser(RegisterDto $data): User
    {
        $user = new User();
        $user->setEmail($data->email);
        $user->setFirstName($data->firstName);
        $user->setLastName($data->lastName);
        $user->setPhone($data->phone);
        $user->setIsActive(true);
        $user->setCreatedAt(new DateTime());

        // Hacher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data->password);
        $user->setPassword($hashedPassword);

        // Définir le niveau du club si fourni
        if ($data->level) {
            $user->setLevel($data->level);
        }

        // Définir le type de participation si fourni
        if ($data->participationType) {
            $user->setParticipationType($data->participationType);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createPayment(User $user, SubscriptionPlan $plan, RegisterDto $data): Payment
    {
        $payment = new Payment();
        $payment->setUser($user);
        $payment->setSubscriptionPlan($plan);
        $payment->setPaymentMethod($data->getPaymentMethodEnum());
        $payment->setReference(strtoupper(uniqid('PAY_')));
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

        // Utiliser le téléphone de paiement si fourni, sinon le téléphone principal
        $phoneForPayment = $data->phonePayment ?? $user->getPhone();
        if (!empty($phoneForPayment)) {
            $phone = PaymentMethod::formatPhoneNumber($phoneForPayment);
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

    private function createClubSubscription(User $user, RegisterDto $data): void
    {
        // Récupérer ou créer le plan "Club Plan"
        $clubPlan = $this->entityManager->getRepository(SubscriptionPlan::class)->findOneBy(['type' => 'club']);
        if (!$clubPlan) {
            $clubPlan = new SubscriptionPlan();
            $clubPlan->setName('Idioma English Club');
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
