<?php

namespace App\Sender;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailSender
{
    const EMAIL = "EMAIL";

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger
    )
    {
    }

    public function support(string $type): bool
    {
        return $type === self::EMAIL;
    }

    public function send(string $subject, string $address, string $template, mixed $context): void
    {
        try {
            $email = (new TemplatedEmail())
                ->to(new Address($address))
                ->subject($subject)
                ->htmlTemplate($template)
                ->context([
                    'data' => $context,
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->warning($e->getMessage(), ['exception' => $e]);
        }
    }
}