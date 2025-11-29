<?php

namespace App\Controller\Admin;

use App\Entity\Rate;
use App\Enum\Currency;
use App\Trait\FrenchActionsTrait;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class RateCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

    public static function getEntityFqcn(): string
    {
        return Rate::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Taux de change')
            ->setEntityLabelInPlural('Taux de change')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['fromCurrency', 'toCurrency', 'rate'])
            ->setPageTitle('index', 'Gestion des taux de change')
            ->setPageTitle('new', 'Créer un nouveau taux')
            ->setPageTitle('edit', 'Modifier le taux')
            ->setPageTitle('detail', 'Détails du taux');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $this->configureFrenchActions($actions);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            ChoiceField::new('fromCurrency')
                ->setLabel('Devise source')
                ->setChoices([
                    'Dollar US (USD)' => Currency::USD,
                    'Franc Congolais (CDF)' => Currency::CDF,
                ])
                ->setRequired(true),
            ChoiceField::new('toCurrency')
                ->setLabel('Devise cible')
                ->setChoices([
                    'Dollar US (USD)' => Currency::USD,
                    'Franc Congolais (CDF)' => Currency::CDF,
                ])
                ->setRequired(true),
            NumberField::new('rate')
                ->setLabel('Taux')
                ->setNumDecimals(6)
                ->setRequired(true)
                ->setHelp('Ex: 2800.000000 pour 1 USD = 2800 CDF'),
            BooleanField::new('isActive')
                ->setLabel('Actif')
                ->setHelp('Seul le dernier taux actif par paire de devises est utilisé'),
            DateTimeField::new('createdAt')
                ->setLabel('Créé le')
                ->hideOnForm(),
            DateTimeField::new('updatedAt')
                ->setLabel('Modifié le')
                ->hideOnForm(),
        ];
    }
}
