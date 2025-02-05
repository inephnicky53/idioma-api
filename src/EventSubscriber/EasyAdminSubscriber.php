<?php

namespace App\EventSubscriber;

use App\Entity\Payment;
use App\Entity\User;
use App\Idioma;
use DateTimeImmutable;
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

        /** @var Payment $entity */
        if ($entity instanceof Payment) {
            ($entity)
                ->setReference(uniqid("PY-{$entity->getType()}-"))
                ->setStatus(Idioma::STATUS_CREATED);
        }
    }
}
