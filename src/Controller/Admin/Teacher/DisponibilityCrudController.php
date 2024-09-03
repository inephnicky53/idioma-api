<?php

namespace App\Controller\Admin\Teacher;

use App\Entity\Disponibility;
use App\Idioma;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class DisponibilityCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Disponibility::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        /*yield AssociationField::new('teacher', "Professeur")
            ->autocomplete()
            ->setColumns(6);*/

        yield ChoiceField::new('day', "Jour disponible")
        ->setTranslatableChoices(Idioma::getDaysList())
        ->setColumns(6);

        yield TextField::new('start', "Heure de début")
            ->setColumns(6);

        yield TextField::new('end', "Heure de fin")
            ->setColumns(6);

        yield BooleanField::new('isActive', "Si actif")

            ->setColumns(6);
    }
}
