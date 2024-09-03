<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\Package;
use App\Idioma;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PackageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Package::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des packages')
            ->setEntityLabelInPlural('Packages')
            ->setEntityLabelInSingular(function (?Package $package, ?string $pageName) {
                return 'edit' === $pageName ? 'le package #' . $package->getId() : 'un package';
            });
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield NumberField::new('hours', "Nombre d'heure")
            ->setColumns(6);

        yield NumberField::new('discount', "Réduction (%)")
            ->setColumns(6);

        yield BooleanField::new('isActive', "Si actif")
            ->setColumns(6);

    }

}
