<?php

namespace App\State\Wallet;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Wallet\WithdrawalRequestInput;
use App\Entity\Payment;
use App\Entity\User;
use App\Exception\InsufficientBalanceException;
use App\Service\Wallet\WalletManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class WithdrawalRequestProcessor implements ProcessorInterface
{
    public function __construct(
        private Security      $security,
        private WalletManager $walletManager
    )
    {
    }

    /**
     * @param WithdrawalRequestInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Payment
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        if (!$user || !($teacher = $user->getTeacher())) {
            throw new AccessDeniedHttpException('You must be a teacher to request a withdrawal.');
        }

        try {
            return $this->walletManager->requestWithdrawal($teacher, $data);
        } catch (InsufficientBalanceException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
    }
}