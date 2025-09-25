<?php

namespace App\EventSubscriber;

use App\Event\TransactionCreatedEvent;
use App\Event\TransactionConfirmedEvent;
use App\Event\TransactionFailedEvent;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class TransactionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
    )
    {
    }

    public function onTransactionCreated(TransactionCreatedEvent $event): void
    {
        $transaction = $event->getTransaction();
        $subject = "Transaction initiée";

        // Vérifier que l'utilisateur existe
        if (!$transaction->getUser() || !$transaction->getUser()->getEmail()) {
            return;
        }

        try {
            $email = (new TemplatedEmail())
                ->to(new Address($transaction->getUser()->getEmail()))
                ->subject($subject)
                ->htmlTemplate('email/transaction_created.html.twig')
                ->context([
                    'subject' => $subject,
                    'data' => ['transaction' => $transaction],
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Log error silently
        }
    }

    public function onTransactionConfirmed(TransactionConfirmedEvent $event): void
    {
        $transaction = $event->getTransaction();
        $subject = "Transaction confirmée !";

        // Vérifier que l'utilisateur existe
        if (!$transaction->getUser() || !$transaction->getUser()->getEmail()) {
            return;
        }

        try {
            $email = (new TemplatedEmail())
                ->to(new Address($transaction->getUser()->getEmail()))
                ->subject($subject)
                ->htmlTemplate('email/transaction_confirmed.html.twig')
                ->context([
                    'subject' => $subject,
                    'data' => ['transaction' => $transaction],
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Log error silently
        }
    }

    public function onTransactionFailed(TransactionFailedEvent $event): void
    {
        $transaction = $event->getTransaction();
        $subject = "Transaction échouée";

        // Vérifier que l'utilisateur existe
        if (!$transaction->getUser() || !$transaction->getUser()->getEmail()) {
            return;
        }

        try {
            $email = (new TemplatedEmail())
                ->to(new Address($transaction->getUser()->getEmail()))
                ->subject($subject)
                ->htmlTemplate('email/transaction_failed.html.twig')
                ->context([
                    'subject' => $subject,
                    'data' => ['transaction' => $transaction],
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Log error silently
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TransactionCreatedEvent::class => 'onTransactionCreated',
            TransactionConfirmedEvent::class => 'onTransactionConfirmed',
            TransactionFailedEvent::class => 'onTransactionFailed',
        ];
    }
}