<?php

namespace App\Service\Transaction;

use App\Dto\CreateOrderInput;
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

            if ($course) {
                $price = $course->isIsPromote() && $course->getAmountPromo() > 0
                    ? $course->getAmountPromo()
                    : $course->getAmount();
                $amount += $price;

                $product = (new OrderProduct())
                    ->setCourse($course)
                    ->setAmount($price);
            } elseif ($teacher && $package) {
                $price = ($teacher->getPrice() * $package->getHours()) * (1 - $package->getDiscount() / 100);
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

        $feeTypes = [Fee::FEE_TRANSACTION_MOBILE, Fee::FEE_SERVICE];
        $amountFee = 0;

        foreach ($feeTypes as $feeType) {
            $fees = $this->em->getRepository(Fee::class)->findBy(['type' => $feeType, 'isActive' => true]);

            foreach ($fees as $fee) {
                if ($fee->isWithinRange($transaction->getAmount())) {
                    $transaction->addFee($fee);
                    $amountFee += ($transaction->getAmount() * $fee->getValue()) / 100;
                }
            }
            if ($feeType === Fee::FEE_TRANSACTION_MOBILE) {
                $amount = $amount - $amountFee;
                $amount = round($amount, 2);
            }
        }

        ($transaction)
            ->setAmount($amount)
            ->setFee($amountFee);

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
     * Vérifie l'état d'une transaction.
     *
     * @throws \Exception
     */
    public function check(int $transactionId): Transaction
    {
        $transaction = $this->em->getRepository(Transaction::class)->find($transactionId);
        if (!$transaction)
            throw new \Exception('Transaction not found.');

        $response = $this->process->check($transaction);

        if ($response['code'] === '0') {
            switch ($response['transaction']['status']) {
                case '0':
                    $transaction->setStatus(Idioma::STATUS_SUCCESS);
                    $this->confirmTransaction($transaction);
                    
                    // Dispatch TransactionConfirmedEvent
                    $this->eventDispatcher->dispatch(new TransactionConfirmedEvent($transaction));
                    
                    // Dispatch OrderConfirmedEvent if order exists
                    $order = $transaction->getCommand();
                    if ($order) {
                        $this->eventDispatcher->dispatch(new OrderConfirmedEvent($order));
                    }
                    break;
                case '1':
                    $transaction->setStatus(Idioma::STATUS_FAILED);
                    
                    // Dispatch TransactionFailedEvent
                    $this->eventDispatcher->dispatch(new TransactionFailedEvent($transaction));
                    break;
                default:
                    $transaction->setStatus(Idioma::STATUS_PROCESS);
                    break;
            }
            $this->em->persist($transaction);
            $this->em->flush();
        } else {
            throw new \Exception('Transaction not found on provider.');
        }

        return $transaction;
    }

    /**
     * Confirme une transaction et met à jour les données associées.
     * @throws \Exception
     */
    public function confirmTransaction(Transaction $transaction): void
    {
        $order = $transaction->getCommand();
        if (!$order)
            throw new \Exception('Order not found for transaction.');

        /** @var User $user */
        $user = $order->getUser();
        if (!$user)
            throw new \Exception('User not found for order.');

        foreach ($order->getProducts() as $product) {
            if ($product->getCourse()) {
                $this->updateUserCourseData($user, $product, $transaction);
                continue;
            }

            $this->updateUserTeacherData($user, $product, $transaction);
            $this->updateTeacherWallet($product, $transaction);
        }

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

        $userTeacher->setHours($product->getPackage()->getHours());
        $userTeacher->setBuyedAt(new \DateTimeImmutable());

        $this->em->persist($userTeacher);
    }

    private function updateTeacherWallet(OrderProduct $product, Transaction $transaction): void
    {
        $teacher = $product->getTeacher();
        $teacher->addToWallet($transaction->getAmount() - $transaction->getFee(), $transaction->getCurrency()->getMin());

        $this->em->persist($teacher);
    }
}
