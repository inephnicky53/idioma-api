<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Trait\FrenchActionsTrait;
use DateTime;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Doctrine\ORM\QueryBuilder;

class ActiveSubscriptionUserCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

    public function __construct(
        private UserRepository $userRepository
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $this->configureFrenchActions($actions);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            EmailField::new('email')->setLabel('Email'),
            TextField::new('firstName')->setLabel('Prénom'),
            TextField::new('lastName')->setLabel('Nom'),
            TextField::new('phone')->setLabel('Téléphone'),
            ArrayField::new('roles')->setLabel('Rôles'),
            BooleanField::new('isActive')->setLabel('Actif'),
            DateTimeField::new('createdAt')->setLabel('Créé le')->hideOnForm(),
            DateTimeField::new('updatedAt')->setLabel('Modifié le')->hideOnForm(),
            DateTimeField::new('lastLoginAt')->setLabel('Dernière connexion')->hideOnForm(),
        ];
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Filter to show only users with active subscriptions for the current month
        $now = new DateTime();
        $startOfMonth = (new DateTime())->modify('first day of this month')->setTime(0, 0, 0);

        $qb->innerJoin('entity.subscriptions', 's')
            ->where('s.status = :status')
            ->andWhere('s.startDate <= :now')
            ->andWhere('s.endDate >= :startOfMonth')
            ->setParameter('status', 'active')
            ->setParameter('now', $now)
            ->setParameter('startOfMonth', $startOfMonth)
            ->distinct();

        return $qb;
    }
}

