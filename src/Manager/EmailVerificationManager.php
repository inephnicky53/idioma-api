<?php

namespace App\Manager;

use App\Entity\User;
use App\Message\SendWelcomeNotificationMessage;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class EmailVerificationManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private EmailService $emailService,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Generates and sends email verification link to a user
     */
    public function sendVerificationEmail(User $user): void
    {
        // Generate verification token
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTime())->modify('+24 hours');

        $user->setEmailVerificationToken($token);
        $user->setEmailVerificationTokenExpiresAt($expiresAt);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Send email
        $this->emailService->sendWelcomeEmail($user, $token);
    }

    /**
     * Verifies email using token
     */
    public function verifyEmail(string $token): User
    {
        // Find user by token
        $user = $this->userRepository->findOneBy(['emailVerificationToken' => $token]);

        if (!$user) {
            throw new NotFoundHttpException('Token invalide ou expiré');
        }

        // Check expiration
        $now = new \DateTime();
        if ($user->getEmailVerificationTokenExpiresAt() < $now) {
            throw new BadRequestHttpException('Token invalide ou expiré');
        }

        // Mark email as verified and clear token
        $shouldSendWelcome = !$user->isPhoneVerified();

        $user->setIsEmailVerified(true);
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationTokenExpiresAt(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        if ($shouldSendWelcome) {
            $this->messageBus->dispatch(new SendWelcomeNotificationMessage(
                userId: $user->getId(),
            ));
        }

        return $user;
    }
}
