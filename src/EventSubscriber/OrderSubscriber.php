<?php

namespace App\EventSubscriber;

use App\Event\OrderCreatedEvent;
use App\Event\OrderConfirmedEvent;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class OrderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
    )
    {
    }

    public function onOrderCreated(OrderCreatedEvent $event): void
    {
        $order = $event->getOrder();
        $subject = "Nouvelle commande créée";

        try {
            $email = (new TemplatedEmail())
                ->to(new Address($order->getUser()->getEmail()))
                ->subject($subject)
                ->htmlTemplate('email/order_created.html.twig')
                ->context([
                    'subject' => $subject,
                    'data' => ['order' => $order],
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Log error silently
        }
    }

    public function onOrderConfirmed(OrderConfirmedEvent $event): void
    {
        $order = $event->getOrder();
        $subject = "Commande confirmée !";

        try {
            $email = (new TemplatedEmail())
                ->to(new Address($order->getUser()->getEmail()))
                ->subject($subject)
                ->htmlTemplate('email/order_confirmed.html.twig')
                ->context([
                    'subject' => $subject,
                    'data' => ['order' => $order],
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Log error silently
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderCreatedEvent::class => 'onOrderCreated',
            OrderConfirmedEvent::class => 'onOrderConfirmed',
        ];
    }
}