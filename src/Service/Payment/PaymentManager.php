<?php

namespace App\Service\Payment;

use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class PaymentManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security               $security,
    )
    {
    }

    public function initiate(Payment $payment)
    {
    }

    public function validate(Payment $payment)
    {
    }
}
