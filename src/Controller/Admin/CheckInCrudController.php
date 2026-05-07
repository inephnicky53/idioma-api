<?php

namespace App\Controller\Admin;

use App\Entity\CheckIn;
use App\Trait\FrenchActionsTrait;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;

class CheckInCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

    public static function getEntityFqcn(): string
    {
        return CheckIn::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $this->configureFrenchActions($actions);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('user')->setLabel('Utilisateur'),
            DateTimeField::new('checkedInAt')->setLabel('Arrivée'),
            DateTimeField::new('checkedOutAt')->setLabel('Départ'),
            TextField::new('location')->setLabel('Lieu'),
            TextEditorField::new('notes')->setLabel('Notes'),
            DateTimeField::new('createdAt')->setLabel('Créé le')->hideOnForm(),
        ];
    }
}

