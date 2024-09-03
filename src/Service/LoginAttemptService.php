<?php


namespace App\Service;

use App\Idioma;
use App\Entity\LoginAttempt;
use App\Entity\User;
use App\Repository\LoginAttemptRepository;

class LoginAttemptService
{
    public function __construct(private readonly LoginAttemptRepository $repository)
    {}

    public function addAttempt(User $user): void
    {
        // TODO : Envoyer un email au bout du Xème essai
        $attempt = (new LoginAttempt())->setUser($user);
        $this->repository->save($attempt, true);
    }

    public function limitReachedFor(User $user): bool
    {
        return $this->repository->countRecentFor($user, 30) >= Idioma::LOGIN_ATTEMPTS;
    }
}
