<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Filtre les notifications à renvoyer par l'API.
 */
final readonly class NotificationQueryExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private Security $security)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (Notification::class !== $resourceClass) return;
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->where(sprintf('%1$s.user = :user', $rootAlias))
                ->andWhere(sprintf('%s.createdAt < NOW()', $rootAlias))
                ->orderBy(sprintf('%s.createdAt', $rootAlias), 'DESC')
                ->setParameter('user', $user);
        }
    }
}
