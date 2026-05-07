<?php

namespace App\Controller\Admin;

use App\Entity\Subscription;
use App\Trait\FrenchActionsTrait;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class SubscriptionCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

    public static function getEntityFqcn(): string
    {
        return Subscription::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $this->configureFrenchActions($actions);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('user')->setLabel('Utilisateur'),
            AssociationField::new('plan')->setLabel('Plan'),
            DateTimeField::new('startDate')->setLabel('Date de début'),
            DateTimeField::new('endDate')->setLabel('Date de fin'),
            ChoiceField::new('status')->setLabel('Statut')->setChoices([
                'Actif' => 'active',
                'Inactif' => 'inactive',
                'En attente' => 'pending',
                'Expiré' => 'expired',
                'Annulé' => 'cancelled',
            ]),
            IntegerField::new('sessionsUsed')->setLabel('Séances utilisées'),
            BooleanField::new('autoRenew')->setLabel('Renouvellement automatique'),
            DateTimeField::new('createdAt')->setLabel('Créé le')->hideOnForm(),
            DateTimeField::new('updatedAt')->setLabel('Modifié le')->hideOnForm(),
        ];
    }
}

