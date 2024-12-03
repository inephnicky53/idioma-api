<?php

namespace App\State\Planning;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Planning;
use App\Service\Planning\PlanningManager;

readonly class PlanningCancelProcessor implements ProcessorInterface
{
    public function __construct(private PlanningManager $manager)
    {
    }

    /**
     * @throws \Exception
     * @var Planning $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Planning
    {
        return $this->manager->cancel($data);
    }
}
