<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Enum\PurchaseType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Provider pour récupérer les paiements de cours en attente
 * Filtre les paiements avec les statuts INIT, WAIT, PROCESS
 */
class PendingCoursePaymentProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if (!$user) {
            return [];
        }

        $payments = $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->where('p.user = :user')
            ->andWhere('p.purchaseType = :type')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('type', PurchaseType::COURSE)
            ->setParameter('statuses', [
                PaymentStatus::INIT,
                PaymentStatus::WAIT,
                PaymentStatus::PROCESS
            ])
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Éviter les doublons : garder un seul paiement par cours (le plus récent)
        $uniquePayments = [];
        $courseIds = [];

        foreach ($payments as $payment) {
            $courseId = $payment->getCourse()?->getId();
            if ($courseId && !in_array($courseId, $courseIds)) {
                $uniquePayments[] = $payment;
                $courseIds[] = $courseId;
            }
        }

        return $uniquePayments;
    }
}

