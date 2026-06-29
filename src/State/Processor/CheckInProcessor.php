<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\CheckIn;
use App\Entity\User;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Création d'un check-in : l'utilisateur est l'utilisateur connecté (jamais la
 * valeur de la requête) et un abonnement actif est requis.
 */
readonly class CheckInProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private SubscriptionRepository $subscriptionRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CheckIn
    {
        if (!$data instanceof CheckIn) {
            throw new \InvalidArgumentException('Expected CheckIn entity');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Vous devez être connecté pour effectuer un check-in');
        }

        if (!$this->subscriptionRepository->findActiveByUser($user)) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Aucun abonnement actif');
        }

        $data->setUser($user);
        // checkedInAt et createdAt sont posés par le constructeur de CheckIn.

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
