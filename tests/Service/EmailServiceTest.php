<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;

class EmailServiceTest extends KernelTestCase
{
    private EmailService $emailService;
    private MailerInterface $mailer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mailer = self::getContainer()->get(MailerInterface::class);
        // Récupère le service déjà câblé par le conteneur (mailer, twig, emails, urls...)
        // plutôt que de l'instancier à la main avec une signature obsolète.
        $this->emailService = self::getContainer()->get(EmailService::class);
    }

    public function testSendPasswordResetEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');

        $resetToken = bin2hex(random_bytes(32));

        // This should not throw an exception
        $this->emailService->sendPasswordResetEmail($user, $resetToken);
        
        $this->assertTrue(true);
    }

    public function testSendWelcomeEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');

        $verificationToken = bin2hex(random_bytes(32));

        // This should not throw an exception
        $this->emailService->sendWelcomeEmail($user, $verificationToken);

        $this->assertTrue(true);
    }
}

