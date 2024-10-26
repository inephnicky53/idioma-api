<?php

namespace App\Service\Transaction;

use App\Dto\CreateOrderInput;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Transaction;
use App\Entity\UserTeacher;
use App\Exception\PaymentException;
use App\Idioma;
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
                ->setPackage($package);
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
            ->setCommand($order);

        $this->em->persist($transaction);
        $this->em->flush();

        try {
            //$result = $this->process->process($order->getTransaction());
            foreach ($order->getProducts() as $product) {
                $userTeacher = $this->em->getRepository(UserTeacher::class)
                    ->findOneBy(['user' => $user, 'teacher' => $product->getTeacher()]);
                if (is_null($userTeacher)) {
                    $userTeacher = (new UserTeacher())
                        ->setTeacher($product->getTeacher())
                        ->setUser($user);
                }
                $userTeacher->setHours($product->getPackage()->getHours());
                $userTeacher->setBuyedAt(new \DateTimeImmutable());
                $this->em->persist($userTeacher);
            }
            $this->em->flush();
        } catch (PaymentException $exception) {
            throw $exception;
        }

        return new JsonResponse([]);
    }
}