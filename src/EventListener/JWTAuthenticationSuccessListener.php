<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuthenticationSuccessHandler.
 *
 * @author Dev Lexik <dev@lexik.fr>
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @final
 */
class JWTAuthenticationSuccessListener
{
    public function __construct(
        private readonly RequestStack $request,
        private readonly EntityManagerInterface $entityManager
    )
    {
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();
        $user->setLastLoginAt(new \DateTimeImmutable());
        $user->setLastLoginIp($this->request->getCurrentRequest()->getClientIp());

        $this->entityManager->flush();
    }
}
