<?php

namespace App\Tests\State\Processor;

use App\Entity\Payment;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Enum\PaymentMethod;
use App\Enum\PurchaseType;
use App\Service\Payment\PaymentManager;
use App\State\Processor\CheckTransactionProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CheckTransactionProcessorTest extends TestCase
{
    private CheckTransactionProcessor $processor;
    private PaymentManager $paymentManager;
    private Security $security;

    protected function setUp(): void
    {
        $this->paymentManager = $this->createMock(PaymentManager::class);
        $this->security = $this->createMock(Security::class);
        $this->processor = new CheckTransactionProcessor($this->paymentManager, $this->security);
    }

    public function testProcessWithValidPayment(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $payment = new Payment();
        $payment->setUser($user);
        $payment->setStatus(PaymentStatus::PROCESS);
        $payment->setPaymentMethod(PaymentMethod::MOBILE);
        $payment->setPurchaseType(PurchaseType::SUBSCRIPTION_CLUB);

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->paymentManager->expects($this->once())
            ->method('check')
            ->with($payment);

        $result = $this->processor->process($payment, $this->createMock(\ApiPlatform\Metadata\Operation::class));

        $this->assertInstanceOf(Payment::class, $result);
        $this->assertEquals($payment, $result);
    }

    public function testProcessWithoutUser(): void
    {
        $payment = new Payment();

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->expectException(AccessDeniedHttpException::class);
        $this->processor->process($payment, $this->createMock(\ApiPlatform\Metadata\Operation::class));
    }

    public function testProcessWithUnauthorizedUser(): void
    {
        $owner = new User();
        $owner->setEmail('owner@example.com');

        $otherUser = new User();
        $otherUser->setEmail('other@example.com');

        $payment = new Payment();
        $payment->setUser($owner);

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($otherUser);

        $this->security->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->processor->process($payment, $this->createMock(\ApiPlatform\Metadata\Operation::class));
    }
}

