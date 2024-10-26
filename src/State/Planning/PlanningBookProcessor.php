<?php

namespace App\State\Planning;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\BookPlanningInput;
use App\Entity\User;
use App\Service\Planning\PlanningManager;

readonly class PlanningBookProcessor implements ProcessorInterface
{
    public function __construct(private PlanningManager $manager)
    {
    }

    /**
     * @throws \Exception
     * @var BookPlanningInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        return $this->manager->book($data);
    }
}
