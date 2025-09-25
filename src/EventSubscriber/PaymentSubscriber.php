<?php

namespace App\EventSubscriber;

use App\Event\PaymentCreatedEvent;
use App\Event\PaymentDeclinedEvent;
use App\Event\PaymentValidatedEvent;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class PaymentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
    )
    {
    }

    public function onPaymentCreated(PaymentCreatedEvent $event): void
    {
        $payment = $event->getPayment();
        $subject = "Nouvelle demande de paiement";

        try {
            $email = (new TemplatedEmail())
                ->to(new Address($payment->getUser()->getEmail()))
                ->subject($subject)
                ->htmlTemplate('email/new_payment.html.twig')
                ->context([
                    'subject' => $subject,
                    'data' => $payment,
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
        }
    }

    public function onPaymentValidated(PaymentValidatedEvent $event): void
    {
        $payment = $event->getPayment();
        $subject = "Votre paiement à été effectué";
        try {
            $email = (new TemplatedEmail())
                ->to(new Address($payment->getUser()->getEmail()))
                ->subject($subject)
                ->htmlTemplate('email/validated_payment.html.twig')
                ->context([
                    'subject' => $subject,
                    'data' => $payment,
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
        }
    }

    public function onPaymentDeclined(PaymentDeclinedEvent $event): void
    {
        $payment = $event->getPayment();
        $subject = "Votre paiement à été refusé";
        try {
            $email = (new TemplatedEmail())
                ->to(new Address($payment->getUser()->getEmail()))
                ->subject($subject)
                ->htmlTemplate('email/declined_payment.html.twig')
                ->context([
                    'subject' => $subject,
                    'data' => $payment,
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentCreatedEvent::class => 'onPaymentCreated',
            PaymentValidatedEvent::class => 'onPaymentValidated',
            PaymentDeclinedEvent::class => 'onPaymentDeclined',
        ];
    }
}
