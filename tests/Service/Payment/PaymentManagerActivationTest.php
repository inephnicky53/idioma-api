<?php

namespace App\Tests\Service\Payment;

use App\Entity\Course;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\SubscriptionPlan;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Message\SendCoursePurchaseNotificationMessage;
use App\Message\SendSubscriptionNotificationMessage;
use App\Service\Payment\PaymentManager;
use App\Service\RateService;
use App\Service\SmsService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Vérifie que l'activation d'un achat notifie bien le client : abonnement créé,
 * abonnement prolongé, et achat de cours.
 */
class PaymentManagerActivationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    /** @var list<object> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->dispatched = [];
    }

    private function makePaymentManager(): PaymentManager
    {
        $container = self::getContainer();

        $bus = new class($this->dispatched) implements MessageBusInterface {
            /** @param list<object> $dispatched */
            public function __construct(private array &$dispatched) {}

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;

                return new Envelope($message);
            }
        };

        return new PaymentManager(
            $this->entityManager,
            $container->get(RateService::class),
            $container->get(Security::class),
            new NullLogger(),
            'flexpay',
            null,
            $container->get(SmsService::class),
            $bus,
        );
    }

    private function persistUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Nephtali');
        $user->setLastName('Landu');
        $user->setPhone('0812345678');
        $user->setPassword('hashed');
        $user->setIsActive(true);
        $user->setCreatedAt(new DateTime());

        $this->entityManager->persist($user);

        return $user;
    }

    private function persistPlan(): SubscriptionPlan
    {
        $plan = new SubscriptionPlan();
        $plan->setName('Idioma English Club');
        $plan->setDescription('Plan Club');
        $plan->setPrice('50.00');
        $plan->setDurationDays(30);
        $plan->setType('club');
        $plan->setSessionsLimit(4);
        $plan->setIsActive(true);

        $this->entityManager->persist($plan);

        return $plan;
    }

    private function persistCourse(): Course
    {
        $course = new Course();
        $course->setTitle('Anglais niveau B1');
        $course->setDescription('Cours complet');
        $course->setPrice('25.00');
        $course->setCurrency(Currency::USD);
        $course->setIsPublished(true);

        $this->entityManager->persist($course);

        return $course;
    }

    private function persistPayment(User $user, SubscriptionPlan|Course $payable): Payment
    {
        $payment = new Payment();
        $payment->setUser($user);
        $payment->setPayable($payable);
        $payment->setPaymentMethod(PaymentMethod::MOBILE);
        $payment->setReference(strtoupper(uniqid('PAY_')));
        $payment->setStatus(PaymentStatus::COMPLETED);
        $payment->setIsSmsSend(false);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    public function testActivatingSubscriptionNotifiesClient(): void
    {
        $user = $this->persistUser('sub@example.com');
        $payment = $this->persistPayment($user, $this->persistPlan());

        $this->makePaymentManager()->activatePurchase($payment);

        $this->assertCount(1, $this->dispatched);
        $message = $this->dispatched[0];
        $this->assertInstanceOf(SendSubscriptionNotificationMessage::class, $message);
        $this->assertFalse($message->renewed, 'Un premier abonnement n\'est pas une prolongation');

        // L'identifiant doit être exploitable par le handler : l'entité a bien été flushée.
        $this->assertNotNull($message->subscriptionId);
        $this->assertNotNull(
            $this->entityManager->getRepository(Subscription::class)->find($message->subscriptionId)
        );
    }

    public function testExtendingSubscriptionIsFlaggedAsRenewal(): void
    {
        $user = $this->persistUser('renew@example.com');
        $plan = $this->persistPlan();
        $manager = $this->makePaymentManager();

        $manager->activatePurchase($this->persistPayment($user, $plan));
        $manager->activatePurchase($this->persistPayment($user, $plan));

        $this->assertCount(2, $this->dispatched);
        $this->assertFalse($this->dispatched[0]->renewed);
        $this->assertTrue($this->dispatched[1]->renewed);
        $this->assertSame(
            $this->dispatched[0]->subscriptionId,
            $this->dispatched[1]->subscriptionId,
            'La prolongation porte sur le même abonnement'
        );
    }

    public function testActivatingCoursePurchaseNotifiesClient(): void
    {
        $user = $this->persistUser('course@example.com');
        $payment = $this->persistPayment($user, $this->persistCourse());

        $this->makePaymentManager()->activatePurchase($payment);

        $this->assertCount(1, $this->dispatched);
        $message = $this->dispatched[0];
        $this->assertInstanceOf(SendCoursePurchaseNotificationMessage::class, $message);
        $this->assertNotNull($message->coursePurchaseId);
    }
}
