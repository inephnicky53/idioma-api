<?php

namespace App\Controller\Admin;

use App\Entity\Rate;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CurrencyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class RateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Rate::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm()
            ->setColumns(6);

        yield TextField::new('reference')
            ->setColumns(6);

        yield NumberField::new('value')
            ->setColumns(6);

        yield AssociationField::new('currency')
            ->onlyOnForms()
            ->setColumns(6);

        yield CurrencyField::new('currency')
            ->hideOnForm()
            ->setColumns(6);
    }
}
