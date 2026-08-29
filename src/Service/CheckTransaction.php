<?php


namespace App\Service;


use App\Entity\Transaction;
use App\Entity\UserCourse;
use App\Entity\UserTeacher;
use App\Event\OrderConfirmedEvent;
use App\Event\TransactionConfirmedEvent;
use App\Event\TransactionFailedEvent;
use App\Idioma;
use App\Service\Transaction\TransactionManager;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CheckTransaction
{
    private EntityManagerInterface $em;
    private OperatorProcess $process;
    private EventDispatcherInterface $eventDispatcher;
    private TransactionManager $transactionManager;

    public function __construct(
        EntityManagerInterface $em,
        OperatorProcess $process,
        EventDispatcherInterface $eventDispatcher,
        TransactionManager $transactionManager
    )
    {
        $this->em = $em;
        $this->process = $process;
        $this->eventDispatcher = $eventDispatcher;
        $this->transactionManager = $transactionManager;
    }

    public function all()
    {
        $txRepository = $this->em->getRepository(Transaction::class);
        $txs = $txRepository->findWaitingsResults();
        $i = 0;
        //dd($txs, Types::DATETIME_MUTABLE);
        foreach ($txs as $transaction) {
            /** @var Transaction $transaction */
            $body = $this->process->check($transaction);
            if ($body !== false) {
                $this->process($body, $transaction);
                $i++;
            }
        }
        return $i;
    }

    public function allWaitings($status = Idioma::STATUS_WAIT, $is_sms_send = true, int $max_result = null)
    {
        $txRepository = $this->em->getRepository(Transaction::class);
        $txs = $txRepository->findWaitingsAllResults($status, $is_sms_send, $max_result);

        $i = 0;
        foreach ($txs as $transaction) {
            /** @var Transaction $transaction */
            $body = $this->process->check($transaction);

            if ($body !== false) {
                $this->process($body, $transaction);
                $i++;
            }
        }
        return $i;
    }

    /**
     * @param mixed $body
     * @param Transaction $transaction
     */
    private function process(mixed $body, Transaction $transaction): void
    {
        if (isset($body['transaction']) && $body['transaction']['status'] === '0') {
            $transaction->setStatus(Idioma::STATUS_SUCCESS);
            $this->approuveOrder($transaction);
        } else {
            $this->disapproveOrder($transaction);
            $transaction->setStatus(Idioma::STATUS_ERROR);
        }
        $transaction->setMessage($body['message'] ?? null);
        $transaction->setRespondedAt(new DateTimeImmutable());

        $this->em->persist($transaction);
        $this->em->flush();
    }

    /**
     * Delegates to TransactionManager so the buyer is actually credited.
     * Setting the order status here directly would mark it confirmed while
     * granting nothing — and TransactionManager::confirmTransaction() treats
     * that status as its "already done" guard, so the credit would be lost.
     *
     * @throws \Exception
     */
    public function approuveOrder(Transaction $transaction): void
    {
        $this->transactionManager->confirmTransaction($transaction);

        $this->eventDispatcher->dispatch(new OrderConfirmedEvent($transaction->getCommand()));

        $this->em->flush();
    }

    public function disapproveOrder(Transaction $transaction): void
    {
        $order = $transaction->getCommand();
        $order->setStatus(Idioma::STATUS_FAILED);

        $this->em->persist($order);
        $this->em->flush();
    }
}
