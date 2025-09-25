<?php

namespace App\Event;

use App\Entity\Order;

readonly class OrderCreatedEvent
{
    public function __construct(private Order $order)
    {
    }

    public function getOrder(): Order
    {
        return $this->order;
    }
}