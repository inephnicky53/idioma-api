<?php

namespace App\Controller\Admin\Transaction;

use App\Entity\Fee;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;

class FeeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Fee::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Gestion des frais')
            ->setPageTitle(Crud::PAGE_NEW, 'Créer un nouveau frais')
            ->setPageTitle(Crud::PAGE_EDIT, fn(Fee $fee) => sprintf('Modifier "%s"', $fee->getName()))
            ->setEntityLabelInPlural('Frais')
            ->setEntityLabelInSingular('Frais')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['name'])
            ->setAutofocusSearch();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::EDIT, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', '#')
            ->hideOnForm();

        yield TextField::new('name', 'Nom du frais')
            ->setRequired(true)
            ->setMaxLength(255)
            ->setHelp('Nom descriptif du frais (ex: "Frais de transaction mobile Orange")')
            ->setColumns(6);

        yield ChoiceField::new('type', 'Type de frais')
            ->setChoices(array_flip(Fee::getTypes()))
            ->setRequired(true)
            ->renderAsBadges([
                Fee::FEE_TRANSACTION_MOBILE => 'success',
                Fee::FEE_TRANSACTION_BANK => 'info',
                Fee::FEE_SERVICE => 'warning',
            ])
            ->setHelp('Type de frais appliqué')
            ->setColumns(6);

        yield NumberField::new('value', 'Valeur (%)')
            ->setRequired(true)
            ->setNumDecimals(2)
            ->setHelp('Pourcentage du frais (ex: 2.5 pour 2,5%)')
            ->setColumns(4);

        yield NumberField::new('min', 'Montant minimum')
            ->setNumDecimals(2)
            ->setHelp('Montant minimum pour appliquer ce frais (optionnel)')
            ->setColumns(4);

        yield NumberField::new('max', 'Montant maximum')
            ->setNumDecimals(2)
            ->setHelp('Montant maximum pour appliquer ce frais (optionnel)')
            ->setColumns(4);

        yield BooleanField::new('isActive', 'Actif')
            ->renderAsSwitch()
            ->setHelp('Désactiver temporairement ce frais sans le supprimer')
            ->setColumns(12);

        yield DateTimeField::new('createdAt', 'Date de création')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm')
            ->onlyOnDetail();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('type')
                ->setChoices(array_flip(Fee::getTypes())))
            ->add(BooleanFilter::new('isActive'))
            ->add(NumericFilter::new('value'))
            ->add(NumericFilter::new('min'))
            ->add(NumericFilter::new('max'));
    }
}
