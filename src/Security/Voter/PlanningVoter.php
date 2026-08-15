<?php

namespace App\Security\Voter;

use App\Entity\Planning;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PlanningVoter extends Voter
{
    public const MANAGE = 'PLANNING_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof Planning;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Planning $planning */
        $planning = $subject;

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($planning->getTeacher()?->getUser() === $user) {
            return true;
        }

        return $planning->getParticipants()->contains($user);
    }
}
