<?php

namespace App\Manager;

use App\Entity\User;
use App\Message\SendWelcomeNotificationMessage;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\WhatsAppService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class OtpManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private EmailService $emailService,
        private WhatsAppService $whatsAppService,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Finds a user by identifier (email or phone) and (re)sends an OTP.
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
     * Generates a 4-digit OTP for a user, stores it (10 min validity) and delivers it.
     * Single source of truth for OTP delivery (registration + resend).
     *
     * WhatsApp est le canal privilégié ; l'email reste le filet de sécurité, sans
     * quoi un numéro invalide ou une indisponibilité de Meta bloquerait l'inscription.
     * L'envoi est synchrone : un OTP différé par une file d'attente n'a pas de sens.
     */
    public function generateAndSendOtp(User $user): void
    {
        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $expiresAt = (new \DateTime())->modify('+10 minutes');

        $user->setPhoneOtp($otp);
        $user->setPhoneOtpExpiresAt($expiresAt);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $sentViaWhatsApp = $this->whatsAppService->sendOtp($user, $otp);

        if (!$sentViaWhatsApp) {
            $this->logger->info('OTP : repli sur l\'email', ['userId' => $user->getId()]);
            $this->emailService->sendOtpEmail($user, $otp);
        }
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

        // Une re-vérification (renvoi de code, second appareil) ne doit pas
        // resouhaiter la bienvenue : on retient l'état d'avant.
        $isFirstVerification = !$user->isPhoneVerified();

        // Mark as verified and clear OTP. On valide aussi l'email : c'est ce flag
        // qui ouvre le login (UserChecker).
        $user->setIsPhoneVerified(true);
        $user->setIsEmailVerified(true);
        $user->setPhoneOtp(null);
        $user->setPhoneOtpExpiresAt(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Le compte est désormais utilisable : on souhaite la bienvenue en
        // arrière-plan pour ne pas retarder la réponse (émission du JWT).
        if ($isFirstVerification) {
            $this->messageBus->dispatch(new SendWelcomeNotificationMessage(
                userId: $user->getId(),
            ));
        }

        return $user;
    }
}
