<?php

namespace App\Manager;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OtpManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private SmsService $smsService
    ) {
    }

    /**
     * Generates and sends phone OTP to a user
     */
    public function sendPhoneOtp(string $identifier): void
    {
        // Find user by phone (primary) or email
        $user = $this->userRepository->findOneBy(['phone' => $identifier]);
        if (!$user) {
            $user = $this->userRepository->findOneBy(['email' => $identifier]);
        }

        if (!$user) {
            throw new NotFoundHttpException('Utilisateur non trouvé');
        }

        if (!$user->getPhone()) {
            throw new BadRequestHttpException('Aucun numéro de téléphone associé à ce compte');
        }

        // Generate 4-digit OTP
        $otp = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $expiresAt = (new \DateTime())->modify('+10 minutes');

        $user->setPhoneOtp($otp);
        $user->setPhoneOtpExpiresAt($expiresAt);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Send via SMS only
        $this->smsService->sendOtpSms($user, $otp);
    }

    /**
     * Verifies phone OTP for a user
     */
    public function verifyPhoneOtp(string $identifier, string $otp): User
    {
        // Find user by phone (primary) or email
        $user = $this->userRepository->findOneBy(['phone' => $identifier]);
        if (!$user) {
            $user = $this->userRepository->findOneBy(['email' => $identifier]);
        }

        if (!$user) {
            throw new NotFoundHttpException('Utilisateur non trouvé');
        }

        // Check OTP
        if ($user->getPhoneOtp() !== $otp) {
            throw new BadRequestHttpException('Code OTP invalide');
        }

        // Check expiration
        $now = new \DateTime();
        if ($user->getPhoneOtpExpiresAt() < $now) {
            throw new BadRequestHttpException('Code OTP expiré');
        }

        // Mark as verified and clear OTP
        $user->setIsPhoneVerified(true);
        $user->setPhoneOtp(null);
        $user->setPhoneOtpExpiresAt(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
