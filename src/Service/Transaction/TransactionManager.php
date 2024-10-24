<?php

namespace App\Service\Transaction;

use App\Dto\CreateOrderInput;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Transaction;
use App\Exception\PaymentException;
use App\Idioma;
use App\Service\OperatorProcess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class TransactionManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OperatorProcess        $process,
        private readonly Security               $security,
    )
    {
    }

    /**
     * @throws PaymentException
     */
    public function create(CreateOrderInput $dto): mixed
    {
        $user = $this->security->getUser();

        $amount = 0;
        $operator = $dto->operator;
        $currency = $dto->currency;
        $status = Idioma::STATUS_CREATED;

        $order = (new Order())
            ->setReference(uniqid("cm-"))
            ->setOperator($operator)
            ->setCurrency($currency)
            ->setStatus($status)
            ->setUser($user);

        foreach ($dto->products as $p) {
            $teacher = $p->teacher;
            $package = $p->package;
            $amount += ($teacher->getPrice() * $package->getHours()) * (1 - $package->getDiscount() / 100);

            $product = (new OrderProduct())
                ->setTeacher($teacher)
                ->setPackage($package)
            ;
            $this->em->persist($product);

            $order->addProduct($product);
        }

        $order->setAmount($amount);

        $this->em->persist($order);
        $this->em->flush();

        $transaction = (new Transaction())
            ->setOperator($operator)
            ->setPhone($dto->phone)
            ->setAmount($amount)
            ->setCurrency($currency)
            ->setStatus($status)
            ->setUser($user)
            ->setCommand($order)
        ;

        $this->em->persist($transaction);
        $this->em->flush();

        return $this->process->process($order->getTransaction());
    }
}