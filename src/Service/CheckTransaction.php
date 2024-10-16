<?php


namespace App\Service;


use App\Entity\Transaction;
use App\Entity\UserCourse;
use App\Entity\UserTeacher;
use App\Idioma;
use App\Kimia;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

class CheckTransaction
{
    private EntityManagerInterface $em;
    private OperatorProcess $process;

    public function __construct(
        EntityManagerInterface $em,
        OperatorProcess $process
    )
    {
        $this->em = $em;
        $this->process = $process;
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
        $transaction->setMessage($body['message']);
        $transaction->setResponsedAt(new DateTimeImmutable());

        $this->em->persist($transaction);
        $this->em->flush();
    }

    public function approuveOrder(Transaction $transaction): void
    {
        $order = $transaction->getCommand();
        $order->setStatus(Idioma::STATUS_SUCCESS);

        $order->getUserCourses()->map(function (UserCourse $course) {
            $course
                ->setIsBuyed(true)
                ->setBuyedAt(new DateTimeImmutable());
        });

        $order->getOrderTeachers()->map(function (UserTeacher $userTeacher) {
            $userTeacher
                ->addHours(1)
                ->setBuyedAt(new DateTimeImmutable());
        });

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
