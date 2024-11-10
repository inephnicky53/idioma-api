<?php

namespace App\EventSubscriber;

use App\Event\PlanningCreatedEvent;
use App\Service\NotificationService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class PlanningSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface     $mailer,
        private NotificationService $notificationService,
    )
    {
    }

    public function onPlanningCreated(PlanningCreatedEvent $event): void
    {
        $planning = $event->getPlanning();
        $subject = "Nouveau booking";
        $this->notificationService->notifyUser($planning->getTeacher()->getUser(), $subject, "Un planning vient d'être enregistré");

        try {
            $email = (new TemplatedEmail())
                ->to(new Address($planning->getTeacher()->getUser()->getEmail()))
                ->subject($subject)
                ->htmlTemplate('email/new_booking.html.twig')
                ->context([
                    'data' => $planning,
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
        }
    }

    public function onPlanningStarted(PlanningCreatedEvent $event): void
    {
        $planning = $event->getPlanning();
        $subject = "Votre programme à commencé";
        $this->notificationService->notifyUser($planning->getTeacher()->getUser(), $subject, '');
        try {
            $email = (new TemplatedEmail())
                ->to(new Address($planning->getTeacher()->getUser()->getEmail()))
                ->subject($subject)
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
