<?php

namespace App\Tests\Service;

use App\Entity\Course;
use App\Entity\CoursePurchase;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\SubscriptionPlan;
use App\Entity\User;
use App\Service\EmailService;
use DateTime;
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

    public function testSendAccountActivatedEmail(): void
    {
        $this->emailService->sendAccountActivatedEmail($this->makeUser());

        $this->assertTrue(true);
    }

    public function testSendSubscriptionActivatedEmail(): void
    {
        $this->emailService->sendSubscriptionActivatedEmail($this->makeSubscription(), renewed: false);
        $this->emailService->sendSubscriptionActivatedEmail($this->makeSubscription(), renewed: true);

        $this->assertTrue(true);
    }

    /**
     * Un plan absent ne doit pas casser le rendu : Twig n'a pas d'opérateur
     * null-safe, les gardes sont donc explicites dans le template.
     */
    public function testSendSubscriptionActivatedEmailWithoutPlan(): void
    {
        $subscription = new Subscription();
        $subscription->setUser($this->makeUser());

        $this->emailService->sendSubscriptionActivatedEmail($subscription);

        $this->assertTrue(true);
    }

    public function testSendSubscriptionExpiredEmail(): void
    {
        $this->emailService->sendSubscriptionExpiredEmail($this->makeSubscription());

        $this->assertTrue(true);
    }

    public function testSendCoursePurchasedEmail(): void
    {
        $course = new Course();
        $course->setTitle('Anglais niveau B1');

        $purchase = new CoursePurchase();
        $purchase->setUser($this->makeUser());
        $purchase->setCourse($course);

        $this->emailService->sendCoursePurchasedEmail($purchase);

        $this->assertTrue(true);
    }

    public function testSendCoursePurchasedEmailWithoutCourse(): void
    {
        $purchase = new CoursePurchase();
        $purchase->setUser($this->makeUser());

        $this->emailService->sendCoursePurchasedEmail($purchase);

        $this->assertTrue(true);
    }

    /**
     * Le reçu doit se rendre même sans plan, sans cours et sans date de paiement.
     */
    public function testSendPaymentReceiptEmailWithMinimalPayment(): void
    {
        $payment = new Payment();
        $payment->setUser($this->makeUser());
        $payment->setReference('PAY_TEST');
        $payment->setAmount('50.00');

        $this->emailService->sendPaymentReceiptEmail($payment);

        $this->assertTrue(true);
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');

        return $user;
    }

    private function makeSubscription(): Subscription
    {
        $plan = new SubscriptionPlan();
        $plan->setName('Idioma English Club');
        $plan->setDurationDays(30);
        $plan->setSessionsLimit(4);

        $subscription = new Subscription();
        $subscription->setUser($this->makeUser());
        $subscription->setPlan($plan);
        $subscription->setStartDate(new DateTime());
        $subscription->setEndDate((new DateTime())->modify('+30 days'));
        $subscription->setStatus('active');

        return $subscription;
    }
}

