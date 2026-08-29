<?php

namespace App\Service\Transaction;

use App\Dto\CreateOrderInput;
use App\Entity\Currency;
use App\Entity\Fee;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\UserCourse;
use App\Entity\UserTeacher;
use App\Event\OrderCreatedEvent;
use App\Event\OrderConfirmedEvent;
use App\Event\TransactionCreatedEvent;
use App\Event\TransactionConfirmedEvent;
use App\Event\TransactionFailedEvent;
use App\Exception\PaymentException;
use App\Idioma;
use App\Service\OperatorProcess;
use App\Service\RateService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class TransactionManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private OperatorProcess        $process,
        private Security               $security,
        private EventDispatcherInterface $eventDispatcher,
        private RateService            $rateService,
    )
    {
    }

    /**
     * Crée une commande et une transaction associée.
     *
     * @throws PaymentException
     */
    public function create(CreateOrderInput $dto): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();
        if (!$user)
            throw new PaymentException('User not authenticated.');

        $amount = 0;
        $operator = $dto->operator;
        $currency = $dto->currency;
        $status = Idioma::STATUS_CREATED;

        $order = (new Order())
            ->setReference(uniqid('cm-', true))
            ->setOperator($operator)
            ->setCurrency($currency)
            ->setStatus($status)
            ->setUser($user);

        foreach ($dto->products as $p) {
            $teacher = $p->teacher;
            $package = $p->package;
            $course = $p->course;

            // Prices are stored in each product's own currency. The buyer picks
            // the currency they pay in, so every line must be converted before
            // it is summed — otherwise a EUR price is charged as the same
            // number of USD (or CDF, which is ~2800× cheaper).
            if ($course) {
                $listPrice = $course->isIsPromote() && $course->getAmountPromo() > 0
                    ? $course->getAmountPromo()
                    : $course->getAmount();
                $price = $this->convertPrice($listPrice, $course->getCurrency(), $currency);
                $amount += $price;

                $product = (new OrderProduct())
                    ->setCourse($course)
                    ->setAmount($price);
            } elseif ($teacher && $package) {
                $listPrice = ($teacher->getPrice() * $package->getHours()) * (1 - $package->getDiscount() / 100);
                $price = $this->convertPrice($listPrice, $teacher->getCurrency(), $currency);
                $amount += $price;

                $product = (new OrderProduct())
                    ->setTeacher($teacher)
                    ->setPackage($package)
                    ->setAmount($price);
            } else {
                throw new PaymentException('Invalid product data.');
            }

            $this->em->persist($product);
            $order->addProduct($product);
        }

        $order->setAmount($amount);

        $this->em->persist($order);
        $this->em->flush();

        // Dispatch OrderCreatedEvent
        $this->eventDispatcher->dispatch(new OrderCreatedEvent($order));

        $transaction = (new Transaction())
            ->setOperator($operator)
            ->setPhone($dto->phone)
            ->setAmount($amount)
            ->setCurrency($currency)
            ->setStatus($status)
            ->setUser($user)
            ->setCommand($order);

        // The transaction fee depends on how the buyer pays; the service fee is
        // the platform's own commission and always applies. FEE_TRANSACTION_BANK
        // used to be dead code: card and PayPal orders were charged the mobile fee.
        $transactionFeeType = $operator === Transaction::OPERATOR_MOBILE
            ? Fee::FEE_TRANSACTION_MOBILE
            : Fee::FEE_TRANSACTION_BANK;

        $amountFee = 0;

        foreach ([$transactionFeeType, Fee::FEE_SERVICE] as $feeType) {
            $fees = $this->em->getRepository(Fee::class)->findBy(['type' => $feeType, 'isActive' => true]);

            foreach ($fees as $fee) {
                if ($fee->isWithinRange($amount)) {
                    $transaction->addFee($fee);
                    $amountFee += ($amount * $fee->getValue()) / 100;
                }
            }
        }

        // The buyer is charged the advertised price. Fees are the platform's
        // commission and come out of the teacher's payout (see
        // updateTeacherWallet) — they are never deducted from what we collect,
        // which previously made us charge less than the order was worth.
        ($transaction)
            ->setAmount(round($amount, 2))
            ->setFee(round($amountFee, 2));

        $this->em->persist($transaction);
        $this->em->flush();

        // Dispatch TransactionCreatedEvent
        $this->eventDispatcher->dispatch(new TransactionCreatedEvent($transaction));

        $result = $this->process->process($transaction);

        return new JsonResponse(array_merge(
            ['message' => 'Transaction created successfully.', 'transactionId' => $transaction->getId()],
            is_array($result) ? $result : []
        ));
    }

    /**
     * Convert a catalogue price into the currency the buyer is paying in.
     *
     * @throws PaymentException when no usable exchange rate is configured — the
     *         order is refused rather than charged at the wrong amount.
     *         Seed rates with `php bin/console app:seed:rates`.
     */
    private function convertPrice(float $listPrice, ?Currency $from, ?Currency $to): float
    {
        try {
            return round($this->rateService->convert($listPrice, $from, $to), 2);
        } catch (\RuntimeException $e) {
            throw new PaymentException($e->getMessage());
        }
    }

    /**
     * Vérifie l'état d'une transaction auprès de FlexPay / PayPal.
     * Mobile money and card are asynchronous: this is the polling counterpart
     * of the FlexPay callback.
     *
     * @throws \Exception
     */
    public function check(int $transactionId): Transaction
    {
        $transaction = $this->em->getRepository(Transaction::class)->find($transactionId);
        if (!$transaction) {
            throw new \Exception('Transaction not found.');
        }

        if ((int) $transaction->getStatus() === Idioma::STATUS_SUCCESS) {
            return $transaction;
        }

        $response = $this->process->check($transaction);
        if (!is_array($response)) {
            throw new \Exception('Transaction not found on provider.');
        }

        $providerCode = (string) ($response['code'] ?? '');
        $txStatus = (string) ($response['transaction']['status'] ?? $response['status'] ?? '');

        if ($providerCode === '0' && $txStatus === '0') {
            $transaction->setStatus(Idioma::STATUS_SUCCESS);
            $transaction->setRespondedAt(new \DateTimeImmutable());
            $this->confirmTransaction($transaction);
            $this->eventDispatcher->dispatch(new TransactionConfirmedEvent($transaction));
            $order = $transaction->getCommand();
            if ($order) {
                $this->eventDispatcher->dispatch(new OrderConfirmedEvent($order));
            }
        } elseif ($providerCode === '0' && $txStatus === '1') {
            $transaction->setStatus(Idioma::STATUS_FAILED);
            $transaction->setRespondedAt(new \DateTimeImmutable());
            $this->eventDispatcher->dispatch(new TransactionFailedEvent($transaction));
        } elseif ($providerCode === '0') {
            $transaction->setStatus(Idioma::STATUS_PROCESS);
        } else {
            // Still waiting for the USSD confirmation / hosted card page.
            $transaction->setStatus(Idioma::STATUS_PROCESS);
        }

        $this->em->persist($transaction);
        $this->em->flush();

        return $transaction;
    }

    /**
     * Confirme une transaction et met à jour les données associées.
     * Idempotent: callback + frontend poll can both land on success.
     *
     * @throws \Exception
     */
    public function confirmTransaction(Transaction $transaction): void
    {
        $order = $transaction->getCommand();
        if (!$order) {
            throw new \Exception('Order not found for transaction.');
        }

        if ((int) $order->getStatus() === Idioma::STATUS_SUCCESS) {
            $transaction->setStatus(Idioma::STATUS_SUCCESS);

            return;
        }

        /** @var User $user */
        $user = $order->getUser();
        if (!$user) {
            throw new \Exception('User not found for order.');
        }

        foreach ($order->getProducts() as $product) {
            if ($product->getCourse()) {
                $this->updateUserCourseData($user, $product, $transaction);
                continue;
            }

            $this->updateUserTeacherData($user, $product, $transaction);
            $this->updateTeacherWallet($product, $transaction);
        }

        $order->setStatus(Idioma::STATUS_SUCCESS);
        $transaction->setStatus(Idioma::STATUS_SUCCESS);
        $this->em->persist($order);
        $this->em->persist($transaction);
        $this->em->flush();
    }

    private function updateUserCourseData(User $user, OrderProduct $product, Transaction $transaction): void
    {
        $course = $product->getCourse();
        $userCourse = $this->em->getRepository(UserCourse::class)
            ->findOneBy(['user' => $user, 'course' => $course]);

        if (!$userCourse) {
            $userCourse = (new UserCourse())
                ->setUser($user)
                ->setCourse($course);
        }

        $userCourse
            ->setIsBuyed(true)
            ->setBuyedAt(new \DateTimeImmutable())
            ->setAmount($product->getAmount() ?? 0)
            ->setCurrency($transaction->getCurrency())
            ->setCommand($transaction->getCommand())
            ->setStatus('active');

        $this->em->persist($userCourse);
    }

    private function updateUserTeacherData(User $user, OrderProduct $product, Transaction $transaction): void
    {
        $teacher = $product->getTeacher();
        $userTeacher = $this->em->getRepository(UserTeacher::class)
            ->findOneBy(['user' => $user, 'teacher' => $teacher]);

        if (!$userTeacher)
            $userTeacher = (new UserTeacher())
                ->setTeacher($teacher)
                ->setUser($user);

        $userTeacher->addHours((float) $product->getPackage()->getHours());
        $userTeacher->setBuyedAt(new \DateTimeImmutable());

        $this->em->persist($userTeacher);
    }

    /**
     * Credit the teacher with this line's price, less its share of the fees.
     *
     * Called once per product, so it must work from the product's own amount:
     * using the whole transaction amount credited the full order to every
     * teacher on a multi-product order. Fees are split proportionally.
     */
    private function updateTeacherWallet(OrderProduct $product, Transaction $transaction): void
    {
        $teacher = $product->getTeacher();
        $lineAmount = $product->getAmount() ?? 0.0;
        $orderAmount = $transaction->getAmount() ?: 0.0;

        $feeShare = $orderAmount > 0
            ? ($transaction->getFee() ?? 0.0) * ($lineAmount / $orderAmount)
            : 0.0;

        $teacher->addToWallet(round($lineAmount - $feeShare, 2), $transaction->getCurrency()->getMin());

        $this->em->persist($teacher);
    }
}
