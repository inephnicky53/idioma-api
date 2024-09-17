<?php

namespace App\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ResetRequestedInput;
use App\Service\User\UserManager;

class ResetRequestedProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly UserManager $manager
    )
    {
    }

    /** @var ResetRequestedInput $data */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        return $this->manager->resetRequested($data);
    }
}
