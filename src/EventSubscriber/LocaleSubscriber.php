<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

readonly class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(private TokenStorageInterface $storage)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return array(
            KernelEvents::REQUEST => array(array('onKernelRequest', 1)),
        );
    }
    public function onKernelRequest(RequestEvent $event): void
    {
        /** @var User $user */
        $user = $this->storage?->getToken()?->getUser();
        $request = $event->getRequest();
        if ($user){
            $request->setLocale($user->getLanguage());
        }
        elseif ($request->headers->has("Accept-Language")) {
            $locale = $request->headers->get('Accept-Language');
            $request->setLocale($locale);
        }
    }
}
