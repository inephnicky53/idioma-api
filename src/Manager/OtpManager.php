<?php

namespace App\Manager;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OtpManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        // L'OTP est envoyé par email tant que l'API SMS n'est pas branchée.
        // TODO: réintroduire l'envoi SMS (SmsService) une fois l'API configurée.
        private EmailService $emailService
    ) {
    }

    /**
     * Finds a user by identifier (email or phone) and (re)sends an OTP by email.
     */
    public function sendPhoneOtp(string $identifier): void
    {
        // Find user by email (primary) or phone
        $user = $this->userRepository->findOneBy(['email' => $identifier]);
        if (!$user) {
            $user = $this->userRepository->findOneBy(['phone' => $identifier]);
        }

        if (!$user) {
            throw new NotFoundHttpException('Utilisateur non trouvé');
        }

        $this->generateAndSendOtp($user);
    }

    /**
     * Generates a 4-digit OTP for a user, stores it (10 min validity) and sends it by email.
     * Single source of truth for OTP delivery (registration + resend).
     */
    public function generateAndSendOtp(User $user): void
    {
        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $expiresAt = (new \DateTime())->modify('+10 minutes');

        $user->setPhoneOtp($otp);
        $user->setPhoneOtpExpiresAt($expiresAt);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->emailService->sendOtpEmail($user, $otp);
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

        // Mark as verified and clear OTP. L'OTP étant délivré par email, on valide
        // aussi l'email : c'est ce flag qui ouvre le login (UserChecker).
        $user->setIsPhoneVerified(true);
        $user->setIsEmailVerified(true);
        $user->setPhoneOtp(null);
        $user->setPhoneOtpExpiresAt(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
