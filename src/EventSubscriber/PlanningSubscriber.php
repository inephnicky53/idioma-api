<?php

namespace App\EventSubscriber;

use App\Event\PlanningCreatedEvent;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Notifier\NotifierInterface;

class PlanningSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly NotifierInterface $notifier
    )
    {
    }

    public function onPlanningCreated(PlanningCreatedEvent $event): void
    {
        $planning = $event->getPlanning();
        try {
            $email = (new TemplatedEmail())
                ->to(new Address($planning->getTeacher()->getUser()->getEmail()))
                ->subject("Nouveau booking")
                ->htmlTemplate('email/new_booking.html.twig')
                ->context([
                    'data' => $planning,
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PlanningCreatedEvent::class => 'onPlanningCreated',
        ];
    }
}
