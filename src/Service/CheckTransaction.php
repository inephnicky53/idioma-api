<?php


namespace App\Service;


use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Enum\PurchaseType;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class CheckTransaction
{
    private EntityManagerInterface $em;
    private OperatorProcess $process;

    public function __construct(
        EntityManagerInterface $em,
        OperatorProcess $process)
    {
        $this->em = $em;
        $this->process = $process;
    }

    /**
     * Vérifie une seule transaction et retourne le résultat
     * @return array|false
     */
    public function checkSinglePayment(Payment $payment): array|false
    {
        return $this->process->check($payment);
    }

    public function all(): int
    {
        $txRepository = $this->em->getRepository(Payment::class);
        $txs = $txRepository->findWaitingsResults();
        $i = 0;
        //dd($txs);
        foreach ($txs as $payment) {
            /** @var Payment $payment */
            $body = $this->process->check($payment);
            //dd($payment,$body);
            if ($body !== false) {
            //if (1) {
                if (isset($body['payment']) && $body['payment']['status'] === '0') {
                //if (1) {
                    $payment->setStatus(PaymentStatus::COMPLETED);
                    $type = $payment->getPurchaseType();
                    if ($type === PurchaseType::SUBSCRIPTION_CLUB)
                        $this->process->sendResult($payment);
                    if ($type === TransactionType::CLUB_MEMBERSHIP)
                        $this->process->sendPalmaresLink($payment);
                } else {
                    //$process->sendResult($payment);
                    $payment->setStatus(TransactionStatus::FAILED);
                }
                $payment->setMessage($body['message']);
                $payment->setResponsedAt(new DateTimeImmutable());

                $this->em->persist($payment);
                $this->em->flush();

                $i++;
            }
        }
        return $i;
    }

    public function allWaitings(TransactionStatus|string $status = null, $is_sms_send = true, int $max_result = null)
    {
        $status = $status ?? TransactionStatus::PENDING;
        $txRepository = $this->em->getRepository(Transaction::class);
        $txs = $txRepository->findWaitingsAllResults($status, $is_sms_send, $max_result);
        $i = 0;
        foreach ($txs as $transaction) {
            /** @var Transaction $transaction */
            $body = $this->process->check($transaction);

            if ($body !== false) {
                if (isset($body['transaction']) && $body['transaction']['status'] === '0') {
                    $transaction->setStatus(TransactionStatus::SUCCESS);
                        //dd($transaction);
                    $type = $transaction->getTransactionType();
                    if ($type === TransactionType::RESULTAT)
                        $this->process->sendResult($transaction);
                    if ($type === TransactionType::DIPLOMA)
                        $this->process->sendOnProcessDiploma($transaction);
                } else {
                    //$process->sendResult($transaction);
                    $transaction->setStatus(TransactionStatus::FAILED);
                }
                $transaction->setMessage($body['message']);
                $transaction->setResponsedAt(new DateTimeImmutable());

                $this->em->persist($transaction);
                $this->em->flush();

                $i++;
            }
        }
        return $i;
    }

}
