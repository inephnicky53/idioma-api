<?php

namespace App\EventSubscriber;

use App\Entity\User;
use DateTimeImmutable;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EasyAdminSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => ['beforePersisted'],
        ];
    }

    public function beforePersisted(BeforeEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if ($entity instanceof User) {
            $entity->setUpdatedAt(new DateTimeImmutable());
        }
    }

    public function beforeCrud(BeforeCrudActionEvent $event)
    {
    }
}
