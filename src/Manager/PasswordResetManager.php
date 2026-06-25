<?php

namespace App\Manager;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordResetManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private EmailService $emailService,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function sendResetPasswordEmail(string $email): void
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return; // Don't reveal whether the user exists
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTime())->modify('+1 hour');

        $user->setResetPasswordToken($token);
        $user->setResetPasswordTokenExpiresAt($expiresAt);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->emailService->sendPasswordResetEmail($user, $token);
    }

    public function resetPassword(string $token, string $password): User
    {
        $user = $this->userRepository->findOneBy(['resetPasswordToken' => $token]);

        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Token invalide ou expiré');
        }

        $now = new \DateTime();
        if ($user->getResetPasswordTokenExpiresAt() < $now) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Token invalide ou expiré');
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $user->setResetPasswordToken(null);
        $user->setResetPasswordTokenExpiresAt(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
