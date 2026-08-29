<?php

namespace App\State\Transaction;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Service\Transaction\TransactionManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CheckTransactionProvider implements ProviderInterface
{
    public function __construct(
        private readonly TransactionManager    $manager,
        private readonly TransactionRepository $transactions,
        private readonly Security              $security,
    )
    {
    }

    /**
     * Ownership is enforced here rather than through the operation's `security`
     * expression: API Platform evaluates that expression *after* the provider has
     * run, and checking a transaction has side effects (it captures the payment
     * and credits the buyer). The check has to happen before those run.
     *
     * @throws \Exception
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $transaction = $this->transactions->find($uriVariables['id']);

        if (!$transaction)
            throw new NotFoundHttpException('Transaction not found.');

        $user = $this->security->getUser();

        if (!$user instanceof User)
            throw new AccessDeniedHttpException('Authentification requise.');

        if (!$this->security->isGranted('ROLE_ADMIN') && $transaction->getUser()?->getId() !== $user->getId())
            throw new AccessDeniedHttpException("Cette transaction ne vous appartient pas.");

        return $this->manager->check($transaction->getId());
    }
}
