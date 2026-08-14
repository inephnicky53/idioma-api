<?php

namespace App\EventSubscriber;

use App\Event\TeacherCreatedEvent;
use App\Event\TeacherValidatedEvent;
use App\Service\SmsService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class TeacherSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private SmsService      $smsService,
        private KernelInterface $kernel,
        private RequestStack    $request
    )
    {
    }

    public function onTeacherCreated(TeacherCreatedEvent $event): void
    {
        $teacher = $event->getTeacher();
        $subject = "Inscription en tant que idiomaster";
        try {
            $email = (new TemplatedEmail())
                ->to(new Address($teacher->getUser()->getEmail()))
                ->subject($subject)
                ->htmlTemplate('email/new_teacher.html.twig')
                ->context([
                    'subject' => $subject,
                    'data' => $teacher,
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
        }

        $message = "Votre demande pour devenir idiomaster est en cours de traitement";
        try {
            $this->smsService->sendBc($teacher->getUser()->getPhone(), $message);
        } catch (\Throwable $e) {
        }
    }

    public function onTeacherValidated(TeacherValidatedEvent $event): void
    {
        $teacher = $event->getTeacher();
        $user = $teacher->getUser();
        try {
            $subject = "Validation du compte idiomaster";
            $email = (new TemplatedEmail())
                ->to(new Address($user->getEmail()))
                ->subject($subject)
                ->htmlTemplate('email/validate_teacher.html.twig')
                ->context([
                    'user' => $user,
                    'subject' => $subject
                ]);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
        }

        $message = "Votre demande pour devenir idiomaster vient d'etre valide, vous pouvez consulter votre compte";

        try {
            $this->smsService->sendBc($user->getPhone(), $message);
        } catch (\Throwable $e) {
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TeacherCreatedEvent::class => 'onTeacherCreated',
            TeacherValidatedEvent::class => 'onTeacherValidated',
        ];
    }
}
