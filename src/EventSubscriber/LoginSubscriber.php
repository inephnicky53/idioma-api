<?php


namespace App\EventSubscriber;


use App\Entity\User;
use App\Event\BadPasswordLoginEvent;
use App\Repository\UserRepository;
use App\Service\LoginAttemptService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoginAttemptService $service,
        private readonly EntityManagerInterface $entityManager
    ){}

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            BadPasswordLoginEvent::class => 'onAuthenticationFailure',
            InteractiveLoginEvent::class => 'onLogin',
        ];
    }

    public function onAuthenticationFailure(BadPasswordLoginEvent $event): void
    {
        $this->service->addAttempt($event->getUser());
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        $event->getRequest()->getClientIp();
        if ($user instanceof User) {
            $ip = $event->getRequest()->getClientIp();
            if ($ip !== $user->getLastLoginIp())
                $user->setLastLoginIp($ip);
            $user->setLastLoginAt(new \DateTimeImmutable());

            $this->entityManager->flush();
        }
    }
}
