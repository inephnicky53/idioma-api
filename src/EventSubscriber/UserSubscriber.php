<?php

namespace App\EventSubscriber;

use App\Event\UserValidatedEvent;
use App\Event\UserPwdResetedEvent;
use App\Event\UserCreatedEvent;
use App\Service\SmsService;
use App\Utils\Generator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MailerInterface             $mailer,
        private readonly SmsService                  $smsService,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly EntityManagerInterface      $em,
        private readonly RequestStack                $request,
        private readonly KernelInterface             $kernel
    )
    {
    }

    public function onUserCreated(UserCreatedEvent $event): void
    {
        $user = $event->getUser();
        dd($user);
        try {
            $subject = "Bienvenue sur Idioma International";
            $email = (new TemplatedEmail())
                ->to(new Address($user->getEmail()))
                ->subject($subject)
                ->htmlTemplate('user/email/welcome.mjml.twig')
                ->context([
                    'subject' => $subject,
                    'teacher' => $user,
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

    public function onUserPwdReseted(UserPwdResetedEvent $event): void
    {
        $user = $event->getUser();
        $plainPassword = Generator::generate(8, Generator::COMPLEXITY_STRONG);

        $user->setPassword(
            $this->userPasswordHasher->hashPassword(
                $user,
                $plainPassword
            )
        );

        //$this->em->persist($user);
        $this->em->flush();

        try {
            $subject = "Réinitialisation de votre mot de passe";
            $email = (new TemplatedEmail())
                ->to(new Address($user->getEmail()))
                ->subject($subject)
                ->htmlTemplate('user/email/reset_password.html.twig')
                ->context([
                    'user' => $user,
                    'subject' => $subject,
                    'plainPassword' => $plainPassword,
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
        }

        $message = "Mode de passe reinitialise, voici votre nouveau mot de passe: " . $plainPassword;
        if ($this->kernel->getEnvironment() == "prod")
            $this->smsService->sendBc($user->getPhone(), $message);

        $message = "Le mot de passe de {$user->getFullname()} est réinitialisé avec succès, l'utilisateur sera notifié";
        if ($this->kernel->getEnvironment() == "dev") {
            $message .= " ({$plainPassword})";
        }
        $this->request->getSession()->getFlashBag()->set('success', $message);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserCreatedEvent::class => 'onUserCreated',
            UserValidatedEvent::class => 'onUserValidated',
            UserPwdResetedEvent::class => 'onUserPwdReseted',
        ];
    }
}
