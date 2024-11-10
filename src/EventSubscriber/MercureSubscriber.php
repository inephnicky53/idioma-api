<?php

namespace App\EventSubscriber;

use App\Event\NotificationCreatedEvent;
use App\Event\NotificationReadEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

readonly class MercureSubscriber implements EventSubscriberInterface
{
    public function __construct(private SerializerInterface $serializer, private HubInterface $hub)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NotificationCreatedEvent::class => 'publishNotification',
            NotificationReadEvent::class => 'onNotificationRead',
        ];
    }

    public function publishNotification(NotificationCreatedEvent $event): void
    {
        $notification = $event->getNotification();
        $channel = 'user/' . $notification->getUser()->getId();

        $update = new Update("/notifications/$channel", $this->serializer->serialize([
            'type' => 'notification',
            'data' => $notification,
        ], 'json', [
            'groups' => ['read:notification'],
            'iri' => false,
        ]), true);
        $this->hub->publish($update);
    }

    public function onNotificationRead(NotificationReadEvent $event): void
    {
        $user = $event->getUser();
        $update = new Update(
            "/notifications/users/{$user->getId()}",
            '{"type": "markAsRead"}',
            true
        );
        $this->hub->publish($update);
    }
}
