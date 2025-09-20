<?php

namespace App\EventSubscriber;

use App\Event\ResetPasswordEvent;
use App\Event\UserValidatedEvent;
use App\Event\UserCreatedEvent;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class UserSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
    )
    {
    }

    public function onUserCreated(UserCreatedEvent $event): void
    {
        $user = $event->getUser();
        try {
            $subject = "Bienvenue sur Idioma International";
            $email = (new TemplatedEmail())
                ->to(new Address($user->getEmail()))
                ->subject($subject)
                ->htmlTemplate('user/email/welcome.mjml.twig')
                ->context([
                    'subject' => $subject,
                    'teacher' => $user,
                    'user' => $user,
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
        }
    }

    public function onUserValidated(UserValidatedEvent $event): void
    {
        $user = $event->getUser();
        try {
            $subject = "Validation du compte utilisateur";
            $email = (new TemplatedEmail())
                ->to(new Address($user->getEmail()))
                ->subject($subject)
                ->htmlTemplate('user/email/validated.mjml.twig')
                ->context([
                    'user' => $user,
                    'subject' => $subject,
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
        }
    }

    public function onUserPwdReseted(ResetPasswordEvent $event): void
    {
        $user = $event->getUser();
        try {
            $subject = "Réinitialisation de votre mot de passe";
            $email = (new TemplatedEmail())
                ->to(new Address($user->getEmail()))
                ->subject($subject)
                ->htmlTemplate('user/email/reset_password.html.twig')
                ->context([
                    'user' => $user,
                    'subject' => $subject,
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserCreatedEvent::class => 'onUserCreated',
            UserValidatedEvent::class => 'onUserValidated',
            ResetPasswordEvent::class => 'onUserPwdReseted',
        ];
    }
}
