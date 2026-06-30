<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\VerifyOtpDto;
use App\Manager\OtpManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class VerifyOtpProcessor implements ProcessorInterface
{
    public function __construct(
        private OtpManager               $otpManager,
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface   $entityManager,
        private LoggerInterface          $logger,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation->getName() !== 'verify_otp') {
            return $data;
        }

        if (!$data instanceof VerifyOtpDto) {
            $this->logger->warning('Data is not VerifyOtpDto', ['type' => get_class($data)]);
            throw new \Exception('Invalid data type');
        }

        $this->logger->info('VerifyOtpProcessor.process called', [
            'identifier' => $data->identifier,
        ]);

        $user = $this->otpManager->verifyPhoneOtp($data->identifier, $data->otp);

        // La vérification réussie connecte l'utilisateur : on émet le JWT et un
        // refresh token (même mécanisme que JWTAuthenticationSuccessHandler, pour
        // que /api/auth/refresh fonctionne).
        $token = $this->jwtManager->create($user);
        $refreshToken = bin2hex(random_bytes(32));
        $user->setRefreshToken($refreshToken);
        $user->setRefreshTokenExpiresAt(new DateTime('+30 days'));
        $this->entityManager->flush();

        // Réponse JSON directe (comme JWTAuthenticationSuccessHandler) : évite
        // qu'API Platform traite le tableau retourné comme une collection à sérialiser.
        return new JsonResponse([
            'message' => 'OTP vérifié avec succès',
            'verified' => true,
            'token' => $token,
            'refreshToken' => $refreshToken,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'phone' => $user->getPhone(),
                'createdAt' => $user->getCreatedAt()?->format('c'),
            ],
        ]);
    }
}
