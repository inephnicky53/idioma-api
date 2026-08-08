<?php

namespace App\Tests\Manager;

use App\Contract\WhatsAppSenderInterface;
use App\Entity\User;
use App\Manager\OtpManager;
use App\Message\SendWelcomeNotificationMessage;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\WhatsAppService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Couvre le parcours demandé : l'OTP part sur WhatsApp à l'inscription, et le
 * message de bienvenue n'est déclenché qu'une fois le code validé.
 */
class OtpManagerTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    private EntityManagerInterface $entityManager;

    /** @var list<object> Messages dispatchés pendant le test */
    private array $dispatched = [];

    /** @var list<array{to: string, template: string, body: list<string>}> */
    private array $whatsAppSent = [];

    private bool $whatsAppShouldSucceed = true;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->dispatched = [];
        $this->whatsAppSent = [];
        $this->whatsAppShouldSucceed = true;
    }

    private function makeOtpManager(): OtpManager
    {
        $container = self::getContainer();

        $sender = new class($this->whatsAppSent, $this->whatsAppShouldSucceed) implements WhatsAppSenderInterface {
            /** @param list<array{to: string, template: string, body: list<string>}> $sent */
            public function __construct(private array &$sent, private bool &$succeeds) {}

            public function sendTemplate(string $to, string $templateName, array $bodyParameters = [], array $extraComponents = []): bool
            {
                if (!$this->succeeds) {
                    return false;
                }

                $this->sent[] = ['to' => $to, 'template' => $templateName, 'body' => $bodyParameters];

                return true;
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };

        $whatsAppService = new WhatsAppService(
            $sender,
            new NullLogger(),
            'idioma_otp',
            'idioma_welcome',
            'idioma_subscription_activated',
            'idioma_course_purchased',
            'idioma_subscription_expired',
            otpTemplateHasCopyButton: true,
        );

        $bus = new class($this->dispatched) implements MessageBusInterface {
            /** @param list<object> $dispatched */
            public function __construct(private array &$dispatched) {}

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;

                return new Envelope($message);
            }
        };

        return new OtpManager(
            $this->entityManager,
            $container->get(UserRepository::class),
            $container->get(EmailService::class),
            $whatsAppService,
            $bus,
            new NullLogger(),
        );
    }

    private function persistUser(string $email, string $phone): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Nephtali');
        $user->setLastName('Landu');
        $user->setPhone($phone);
        $user->setPassword('hashed');
        $user->setIsActive(true);
        $user->setCreatedAt(new \DateTime());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function testOtpIsSentOverWhatsAppAndSkipsEmail(): void
    {
        $user = $this->persistUser('otp-wa@example.com', '0812345678');

        $this->makeOtpManager()->generateAndSendOtp($user);

        $this->assertCount(1, $this->whatsAppSent);
        $this->assertSame('idioma_otp', $this->whatsAppSent[0]['template']);
        $this->assertSame('0812345678', $this->whatsAppSent[0]['to']);
        $this->assertSame([$user->getPhoneOtp()], $this->whatsAppSent[0]['body']);

        // WhatsApp ayant abouti, l'email de repli ne doit pas partir.
        self::assertEmailCount(0);
    }

    public function testOtpFallsBackToEmailWhenWhatsAppFails(): void
    {
        $this->whatsAppShouldSucceed = false;
        $user = $this->persistUser('otp-fallback@example.com', '0812345679');

        $this->makeOtpManager()->generateAndSendOtp($user);

        $this->assertSame([], $this->whatsAppSent);
        self::assertEmailCount(1);
    }

    public function testFirstVerificationDispatchesWelcomeNotification(): void
    {
        $manager = $this->makeOtpManager();
        $user = $this->persistUser('verify@example.com', '0812345680');
        $manager->generateAndSendOtp($user);

        $verified = $manager->verifyPhoneOtp('0812345680', $user->getPhoneOtp());

        $this->assertTrue($verified->isPhoneVerified());
        $this->assertCount(1, $this->dispatched);
        $this->assertInstanceOf(SendWelcomeNotificationMessage::class, $this->dispatched[0]);
        $this->assertSame($user->getId(), $this->dispatched[0]->userId);
    }

    public function testSecondVerificationDoesNotResendWelcome(): void
    {
        $manager = $this->makeOtpManager();
        $user = $this->persistUser('verify-twice@example.com', '0812345681');

        $manager->generateAndSendOtp($user);
        $manager->verifyPhoneOtp('0812345681', $user->getPhoneOtp());

        // Nouveau code (renvoi), puis seconde vérification du même compte.
        $manager->generateAndSendOtp($user);
        $manager->verifyPhoneOtp('0812345681', $user->getPhoneOtp());

        $this->assertCount(1, $this->dispatched, 'La bienvenue ne doit partir qu\'une seule fois');
    }
}
