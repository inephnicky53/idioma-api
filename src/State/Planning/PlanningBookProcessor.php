<?php

namespace App\State\Planning;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Planning;
use App\Exception\InsufficientHoursException;
use App\Service\Planning\PlanningManager;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class PlanningBookProcessor implements ProcessorInterface
{
    public function __construct(private PlanningManager $manager)
    {
    }

    /**
     * @throws \Exception
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Planning
    {
        try {
            return $this->manager->book($data);
        } catch (InsufficientHoursException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }
}
