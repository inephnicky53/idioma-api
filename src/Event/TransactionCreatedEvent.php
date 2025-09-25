<?php

namespace App\Event;

use App\Entity\Transaction;

readonly class TransactionCreatedEvent
{
    public function __construct(private Transaction $transaction)
    {
    }

    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }
}