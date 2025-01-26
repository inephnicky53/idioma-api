<?php

namespace App\Controller\Admin\Transaction;

use App\Entity\Fee;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class FeeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Fee::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', '#')->hideOnForm();
        yield TextField::new('name', 'Nom');
        yield ChoiceField::new('type', 'Type')
            ->setChoices(array_flip(Fee::getTypes()));
        yield NumberField::new('value', 'Valeur (%)');
        yield NumberField::new('min', 'Montant minimum');
        yield NumberField::new('max', 'Montant maximum');
    }
}
