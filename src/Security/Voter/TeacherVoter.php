<?php

namespace App\Security\Voter;

use App\Entity\Teacher;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Only the teacher themselves (or an admin) can edit their own profile,
 * pricing, or disponibilities — a logged-in student must not be able to
 * PATCH another teacher's hourly rate.
 */
class TeacherVoter extends Voter
{
    public const MANAGE = 'TEACHER_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof Teacher;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Teacher $teacher */
        $teacher = $subject;

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return $teacher->getUser() === $user;
    }
}
