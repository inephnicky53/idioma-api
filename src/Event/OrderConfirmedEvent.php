<?php

namespace App\Event;

use App\Entity\Order;

readonly class OrderConfirmedEvent
{
    public function __construct(private Order $order)
    {
    }

    public function getOrder(): Order
    {
        return $this->order;
    }
}