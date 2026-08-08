<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\CoursePurchase;
use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Enum\PurchaseType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Paiements de cours en attente : on ne retient un cours que si son
 * paiement le plus récent (tous statuts confondus) est encore INIT/WAIT/PROCESS.
 * Évite qu'un ancien paiement WAIT bloque le cours après annulation du dernier.
 */
class PendingCoursePaymentProvider implements ProviderInterface
{
    private const PENDING_STATUSES = [
        PaymentStatus::INIT,
        PaymentStatus::WAIT,
        PaymentStatus::PROCESS,
    ];

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

        /** @var Payment[] $allCoursePayments */
        $allCoursePayments = $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->where('p.user = :user')
            ->andWhere('p.purchaseType = :type')
            ->andWhere('p.course IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('type', PurchaseType::COURSE)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $ownedCourseIds = array_map(
            'intval',
            $this->entityManager->getRepository(CoursePurchase::class)
                ->createQueryBuilder('cp')
                ->select('IDENTITY(cp.course)')
                ->where('cp.user = :user')
                ->andWhere('cp.isActive = true')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleColumnResult()
        );

        /** @var array<int, Payment> $latestByCourseId */
        $latestByCourseId = [];
        foreach ($allCoursePayments as $payment) {
            $courseId = $payment->getCourse()?->getId();
            if ($courseId && !isset($latestByCourseId[$courseId])) {
                $latestByCourseId[$courseId] = $payment;
            }
        }

        $pending = [];
        foreach ($latestByCourseId as $courseId => $payment) {
            if (in_array($courseId, $ownedCourseIds, true)) {
                continue;
            }
            if (in_array($payment->getStatus(), self::PENDING_STATUSES, true)) {
                $pending[] = $payment;
            }
        }

        return $pending;
    }
}
