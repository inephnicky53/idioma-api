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
        $this->emailService = new EmailService($this->mailer);
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

        // This should not throw an exception
        $this->emailService->sendWelcomeEmail($user);
        
        $this->assertTrue(true);
    }
}

