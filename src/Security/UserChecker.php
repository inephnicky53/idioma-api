<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Check if user is active
        if (!$user->isActive()) {
            throw new DisabledException('User account is disabled.');
        }

        // Empêche la connexion tant que le compte n'a pas été vérifié par OTP
        // (envoyé par email à l'inscription). Évite de contourner l'OTP via /login_check.
        if (!$user->isEmailVerified()) {
            throw new CustomUserMessageAuthenticationException(
                'Veuillez vérifier votre compte avec le code envoyé par email.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // You can add additional checks here if needed
        // For example, check if the user's password needs to be changed
    }
}

