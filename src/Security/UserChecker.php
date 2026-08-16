<?php


namespace App\Security;

use App\Entity\User;
use App\Exception\TooManyBadCredentialsException;
use App\Exception\UserBannedException;
use App\Exception\UserNotFoundException;
use App\Service\LoginAttemptService;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly LoginAttemptService $loginAttemptService,
    ){}

    /**
     * Vérifie que l'utilisateur a le droit de se connecter.
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && $user->isBanned()) {
            throw new UserBannedException();
        }

        if ($user instanceof User && $this->loginAttemptService->limitReachedFor($user)) {
            throw new TooManyBadCredentialsException();
        }

    }

    /**
     * Vérifie que l'utilisateur est connecté et a le droit de continuer.
     */
    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User || null === $user->getConfirmationToken()) {
            return;
        }

        // Admins can access the back-office even if email verification is pending.
        // JWT login (/api/login) does not use this checker, so blocking admins here
        // would reject valid credentials on the Symfony login form only.
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        throw new UserNotFoundException();
    }
}
