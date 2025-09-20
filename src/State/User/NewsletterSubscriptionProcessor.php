<?php

namespace App\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\NewsletterSubscriptionInput;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class NewsletterSubscriptionProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        /** @var NewsletterSubscriptionInput $data */
        /** @var User $user */
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \Exception('Utilisateur non authentifié');
        }

        $user->setIsNewsletterSubscribed($data->isSubscribed);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}