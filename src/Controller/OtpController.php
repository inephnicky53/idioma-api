<?php

namespace App\Controller;

use App\Dto\SendOtpDto;
use App\Dto\VerifyOtpDto;
use App\Entity\User;
use App\Service\EmailService;
use App\Service\SmsService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

class OtpController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailService $emailService,
        private SmsService $smsService
    ) {}

    #[Route('/api/auth/send-otp', name: 'api_send_otp', methods: ['POST'])]
    public function sendOtp(#[MapRequestPayload] SendOtpDto $dto): JsonResponse
    {
        $identifier = $dto->identifier;

        // Find user by email or phone
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $identifier]);
        if (!$user) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['phone' => $identifier]);
        }

        if (!$user) {
            return new JsonResponse(['message' => 'Utilisateur non trouvé'], 404);
        }

        // Generate OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = (new DateTime())->modify('+10 minutes');

        $user->setOtp($otp);
        $user->setOtpExpiresAt($expiresAt);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Send OTP via email (always)
        $this->emailService->sendOtpEmail($user, $otp);

        // Send OTP via SMS if phone is available
        if ($user->getPhone()) {
            $this->smsService->sendOtpSms($user, $otp);
        }

        return new JsonResponse(['message' => 'OTP envoyé avec succès']);
    }

    #[Route('/api/auth/verify-otp', name: 'api_verify_otp', methods: ['POST'])]
    public function verifyOtp(#[MapRequestPayload] VerifyOtpDto $dto): JsonResponse
    {
        $identifier = $dto->identifier;
        $otp = $dto->otp;

        // Find user by email or phone
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $identifier]);
        if (!$user) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['phone' => $identifier]);
        }

        if (!$user) {
            return new JsonResponse(['message' => 'Utilisateur non trouvé'], 404);
        }

        // Check OTP
        if ($user->getOtp() !== $otp) {
            return new JsonResponse(['message' => 'Code OTP invalide'], 400);
        }

        // Check expiration
        $now = new DateTime();
        if ($user->getOtpExpiresAt() < $now) {
            return new JsonResponse(['message' => 'Code OTP expiré'], 400);
        }

        // Mark as verified
        $user->setIsVerified(true);
        $user->setOtp(null);
        $user->setOtpExpiresAt(null);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Send welcome notifications (email only for now)
        $this->emailService->sendWelcomeEmail($user);

        return new JsonResponse(['message' => 'OTP vérifié avec succès', 'verified' => true]);
    }
}
