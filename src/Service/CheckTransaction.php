<?php


namespace App\Service;


use App\Entity\Transaction;
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
    private function process(mixed $body, Transaction $transaction)
    {
        if (isset($body['transaction']) && $body['transaction']['status'] === '0') {
            $transaction->setStatus(Idioma::STATUS_SUCCESS);
            $this->process->approuveOrder($transaction);
        } else {
            $this->process->desapprouveOrder($transaction);
            $transaction->setStatus(Idioma::STATUS_ERROR);
        }
        $transaction->setMessage($body['message']);
        $transaction->setResponsedAt(new DateTimeImmutable());

        $this->em->persist($transaction);
        $this->em->flush();
    }

}
