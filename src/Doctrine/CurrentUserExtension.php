<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Restreint automatiquement les collections sensibles (paiements, abonnements)
 * aux enregistrements appartenant à l'utilisateur authentifié.
 *
 * Sans ce filtre, GET /api/payments et GET /api/subscriptions exposaient les
 * données de TOUS les utilisateurs à n'importe quel compte (fuite IDOR).
 * Les administrateurs conservent la vue complète.
 */
final class CurrentUserExtension implements QueryCollectionExtensionInterface
{
    /** Entités filtrées par propriétaire (champ `user`). */
    private const OWNED_RESOURCES = [
        Payment::class,
        Subscription::class,
    ];

    public function __construct(private readonly Security $security) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if (!in_array($resourceClass, self::OWNED_RESOURCES, true)) {
            return;
        }

        // Les administrateurs voient tout.
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $user = $this->security->getUser();
        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (!$user instanceof User) {
            // Aucun utilisateur authentifié : ne rien renvoyer plutôt que tout exposer.
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $queryBuilder
            ->andWhere(sprintf('%s.user = :current_user', $rootAlias))
            ->setParameter('current_user', $user);
    }
}
