<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Trait\FrenchActionsTrait;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

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
}

