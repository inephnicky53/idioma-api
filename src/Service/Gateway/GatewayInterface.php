<?php

namespace App\Service\Gateway;

use App\Entity\Transaction;

interface GatewayInterface
{
    public function process(Transaction $transaction);

}