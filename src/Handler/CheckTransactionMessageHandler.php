<?php

namespace App\Handler;

use App\Event\CheckTransactionEventMessage;
use App\Idioma;
use App\Repository\TransactionRepository;
use App\Service\Transaction\TransactionManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CheckTransactionMessageHandler
{
    public function __construct(
        private TransactionManager    $transactionManager,
        private TransactionRepository $transactionRepository,
    )
    {
    }


    /**
     * @throws \Exception
     */
    public function __invoke(CheckTransactionEventMessage $event): void
    {
        $transactions = $this->transactionRepository->findBy(['status' => Idioma::STATUS_PROCESS]);
        foreach ($transactions as $transaction) {
            $this->transactionManager->check($transaction->getId());
        }
    }
}