<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Enum\PurchaseType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Annule un paiement en attente (statut CANCELLED) au lieu de le supprimer.
 */
readonly class CancelPaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Payment
    {
        if (!$data instanceof Payment) {
            throw new \InvalidArgumentException('Expected Payment entity');
        }

        $user = $this->security->getUser();
        if (!$user) {
            throw new AccessDeniedHttpException('Vous devez être connecté');
        }

        if ($data->getUser() !== $user && !$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('Vous n\'avez pas accès à ce paiement');
        }

        if ($data->getStatus()->isFinal()) {
            throw new BadRequestHttpException('Ce paiement est déjà finalisé et ne peut pas être annulé');
        }

        $data->setStatus(PaymentStatus::CANCELLED);
        $data->setResponsedAt(new DateTimeImmutable());

        $existingNotes = $data->getNotes() ?? '';
        $data->setNotes(trim($existingNotes . "\nAnnulé par l'utilisateur le " . date('d/m/Y H:i')));

        $this->entityManager->persist($data);

        // Annuler aussi les autres tentatives en attente pour le même cours
        if ($data->getPurchaseType() === PurchaseType::COURSE && $data->getCourse()) {
            $this->cancelOtherPendingCoursePayments($data);
        }

        $this->entityManager->flush();

        return $data;
    }

    private function cancelOtherPendingCoursePayments(Payment $cancelled): void
    {
        $others = $this->entityManager->getRepository(Payment::class)->findBy([
            'user' => $cancelled->getUser(),
            'course' => $cancelled->getCourse(),
            'purchaseType' => PurchaseType::COURSE,
        ]);

        foreach ($others as $payment) {
            if ($payment->getId() === $cancelled->getId() || $payment->getStatus()->isFinal()) {
                continue;
            }

            $payment->setStatus(PaymentStatus::CANCELLED);
            $payment->setResponsedAt(new DateTimeImmutable());
            $notes = $payment->getNotes() ?? '';
            $payment->setNotes(trim($notes . "\nAnnulé avec la demande #" . $cancelled->getId()));
            $this->entityManager->persist($payment);
        }
    }
}
