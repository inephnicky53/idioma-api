<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Idioma;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des commandes')
            ->setEntityLabelInPlural('Commandes')
            ->setEntityLabelInSingular(function (?Order $order, ?string $pageName) {
                return 'edit' === $pageName ? $order : 'une commande';
            });
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield ChoiceField::new('status')
            ->renderAsBadges(Idioma::getStatusBadge())
            ->setChoices(array_flip(Idioma::getStatusListForView()))
            //->hideOnIndex()
            ->setColumns(6);

        yield MoneyField::new('amount', "Montant")
            ->setCurrencyPropertyPath('currency')
            ->setColumns(6)
            ->setCustomOption('storedAsCents', false);

        yield AssociationField::new('currency', "Dévise")
            ->onlyOnForms()
            ->setColumns(6);

        yield DateTimeField::new('createdAt')
            ->setColumns(6);
    }

}
