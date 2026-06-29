<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\CheckIn;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Check-out d'un check-in existant. La propriété est déjà vérifiée par
 * l'expression de sécurité de l'opération (object.getUser() == user).
 */
readonly class CheckoutProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CheckIn
    {
        if (!$data instanceof CheckIn) {
            throw new \InvalidArgumentException('Expected CheckIn entity');
        }

        $data->setCheckedOutAt(new DateTime());
        $this->entityManager->flush();

        return $data;
    }
}
