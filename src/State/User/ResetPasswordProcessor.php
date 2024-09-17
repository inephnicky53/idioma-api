<?php

namespace App\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ResetRequestedInput;
use App\Service\User\UserManager;

class ResetPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly UserManager $manager
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        return $this->manager->resetPassword($data);
    }
}
