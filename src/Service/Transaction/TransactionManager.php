<?php

namespace App\Service\Transaction;

use App\Dto\CreateOrderInput;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Transaction;
use App\Exception\PaymentException;
use App\Idioma;
use App\Repository\OrderRepository;
use App\Service\Gateway\PaypalGateway;
use App\Service\OperatorProcess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

class TransactionManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OperatorProcess        $process,
        private readonly Security               $security,
        private readonly PaypalGateway          $paypalGateway,
        private readonly OrderRepository        $orderRepository,
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
            $amount += ($teacher->getPrice() * $package->getHours()) / $package->getDiscount();
            $product = (new OrderProduct())
                ->setTeacher($teacher)
                ->setPackage($package)
            ;
            $order->addProduct($product);
        }

        $order->setAmount($amount);

        $transaction = (new Transaction())
            ->setOperator($operator)
            ->setAmount($amount)
            ->setCurrency($currency)
            ->setStatus($status)
            ->setUser($user);

        return $this->process->process($transaction);
    }
}