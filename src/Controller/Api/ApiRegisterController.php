<?php

namespace App\Controller\Api;

use App\Entity\OTP;
use App\Entity\User;
use App\Event\UserCreatedEvent;
use App\Repository\OTPRepository;
use App\Repository\UserRepository;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ApiRegisterController extends AbstractController
{
    private const DEFAULT_IP_FOR_LOCAL = '41.78.192.90';
    private const OTP_LENGTH = 4;
    private const OTP_EXPIRY_MINUTES = 2;
    
    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly UserRepository $userRepository,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ValidatorInterface $validator,
        private readonly OTPRepository $otpRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) 
    {
    }

    public function __invoke(
        Request $request,
        User $data,
        SmsService $smsService
    ): JsonResponse {
        try {

            $validationErrors = $this->validateUserData($data);
            if ($validationErrors) {
                return $validationErrors;
            }

            if ($this->userRepository->findOneBy(['email' => $data->getEmail()])) {
                return $this->json([
                    'error' => 'Un utilisateur avec cet email existe déjà.'
                ], Response::HTTP_CONFLICT);
            }

            if ($data->getPhone() && $this->userRepository->findOneBy(['phone' => $data->getPhone()])) {
                return $this->json([
                    'error' => 'Un utilisateur avec ce numéro de téléphone existe déjà.'
                ], Response::HTTP_CONFLICT);
            }

            $this->initializeUserGeoLocation($data, $request);

            $this->hashUserPassword($data);

            $this->entityManager->beginTransaction();
            
            try {
                // Sauvegarde de l'utilisateur
                $this->userRepository->add($data, true);

                // Gestion de l'OTP si un numéro de téléphone est fourni
                $otpSent = false;
                if ($data->getPhone()) {
                    $otpSent = $this->handleOTPGeneration($data, $smsService);
                }

                // Génération du token JWT
                $token = $this->jwtManager->create($data);

                // Dispatch de l'événement de création d'utilisateur
                $this->dispatcher->dispatch(new UserCreatedEvent($data));

                $this->entityManager->commit();

                $response = [
                    'token' => $token,
                    'user' => [
                        'id' => $data->getId(),
                        'email' => $data->getEmail(),
                        'phone' => $data->getPhone()
                    ]
                ];

                if ($otpSent) {
                    $response['message'] = 'Inscription réussie. Un code de vérification a été envoyé par SMS.';
                    $response['otp_required'] = true;
                } else {
                    $response['message'] = 'Inscription réussie.';
                }

                $this->logger->info('Nouvel utilisateur inscrit', [
                    'user_id' => $data->getId(),
                    'email' => $data->getEmail(),
                    'ip' => $request->getClientIp()
                ]);

                return $this->json($response, Response::HTTP_CREATED);
                
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                throw $e;
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'inscription', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $data->getEmail() ?? 'unknown',
                'ip' => $request->getClientIp()
            ]);

            return $this->json([
                'error' => 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function validateUserData(User $data): ?JsonResponse
    {
        $errors = $this->validator->validate($data);

        if (count($errors) > 0) {
            return $this->json([
                'error' => 'Données invalides',
                'violations' => $this->formatValidationErrors($errors)
            ], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    private function formatValidationErrors(ConstraintViolationListInterface $errors): array
    {
        $formattedErrors = [];
        foreach ($errors as $error) {
            $formattedErrors[] = [
                'property' => $error->getPropertyPath(),
                'message' => $error->getMessage(),
                'invalid_value' => $error->getInvalidValue()
            ];
        }
        return $formattedErrors;
    }

    private function initializeUserGeoLocation(User $data, Request $request): void
    {
        $ip = $this->getClientIp($request);
        
        try {
            $data->initGeoIp($ip);
        } catch (\Exception $e) {
            $this->logger->warning('Impossible d\'initialiser la géolocalisation', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            // Continue sans géolocalisation en cas d'erreur
        }
    }

    private function getClientIp(Request $request): string
    {
        $clientIp = $request->getClientIp();
        
        // Utiliser une IP par défaut pour les environnements locaux
        if (in_array($clientIp, ['127.0.0.1', '::1', null], true)) {
            return self::DEFAULT_IP_FOR_LOCAL;
        }
        
        return $clientIp;
    }

    private function hashUserPassword(User $data): void
    {
        $hashedPassword = $this->userPasswordHasher->hashPassword(
            $data,
            $data->getPassword()
        );
        $data->setPassword($hashedPassword);
    }

    private function handleOTPGeneration(User $data, SmsService $smsService): bool
    {
        try {
            $this->otpRepository->deleteBy($data, OTP::TYPE_USER);

            $otp = OTP::generate(
                $data,
                self::OTP_LENGTH,
                self::OTP_EXPIRY_MINUTES,
                OTP::TYPE_USER,
                $data->getPhone(),
                $data->getId()
            );
            
            $this->otpRepository->add($otp, true);

            $message = sprintf(
                'Bienvenue ! Votre code de vérification Idioma est : %s. Ce code expire dans %d minutes.',
                $otp->getPass(),
                self::OTP_EXPIRY_MINUTES
            );
            
            $smsService->sendBc($data->getPhone(), $message);

            $this->logger->info('OTP envoyé avec succès', [
                'user_id' => $data->getId(),
                'phone' => $data->getPhone()
            ]);

            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'OTP', [
                'user_id' => $data->getId(),
                'phone' => $data->getPhone(),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}