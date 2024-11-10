<?php

namespace App\Service\Transaction;

use App\Dto\CreateOrderInput;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\UserTeacher;
use App\Exception\PaymentException;
use App\Idioma;
use App\Service\OperatorProcess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class TransactionManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private OperatorProcess        $process,
        private Security               $security,
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

        $this->process->process($order->getTransaction());

        return new JsonResponse([]);
    }

    /**
     * @throws \Exception
     */
    public function check($transactionId)
    {
        $transaction = $this->em->getRepository(Transaction::class)->find($transactionId);
        if (is_null($transaction))
            throw new \Exception("Transaction not found");

        $response = $this->process->check($transaction);

        if ($response['code'] === "0") {
            if ($response['transaction']['status'] === "0")
                $this->confirmTransaction($transaction);
            else if ($response['transaction']['status'] === "1") {
                $transaction->setStatus(Idioma::STATUS_FAILED);
            } else {
                $transaction->setStatus(Idioma::STATUS_PROCESS);
            }
            $this->em->persist($transaction);
            $this->em->flush();
        } else {
            throw new \Exception("Transaction not found on provider");
        }

        return $transaction;
    }

    public function confirmTransaction(Transaction $transaction): void
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $order = $transaction->getCommand();
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


    }
}