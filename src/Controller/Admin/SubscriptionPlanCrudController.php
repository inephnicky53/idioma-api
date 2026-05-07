<?php

namespace App\Controller\Admin;

use App\Entity\SubscriptionPlan;
use App\Trait\FrenchActionsTrait;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class SubscriptionPlanCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

    public static function getEntityFqcn(): string
    {
        return SubscriptionPlan::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $this->configureFrenchActions($actions);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name')->setLabel('Nom'),
            TextEditorField::new('description')->setLabel('Description'),
            MoneyField::new('price')
                ->setLabel('Prix')
                ->setCurrency('USD')
                ->setStoredAsCents(false),
            IntegerField::new('durationDays')->setLabel('Durée (jours)'),
            ChoiceField::new('type')->setLabel('Type')->setChoices([
                'Club' => 'club',
                'Formation' => 'formation',
                'Les deux' => 'both',
            ]),
            IntegerField::new('sessionsLimit')->setLabel('Limite de séances'),
            BooleanField::new('isActive')->setLabel('Actif'),
            DateTimeField::new('createdAt')->setLabel('Créé le')->hideOnForm(),
            DateTimeField::new('updatedAt')->setLabel('Modifié le')->hideOnForm(),
        ];
    }
}

