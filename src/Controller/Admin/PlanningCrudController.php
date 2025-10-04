<?php

namespace App\Controller\Admin;

use App\Entity\Planning;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PlanningCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Planning::class;
    }


    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield AssociationField::new('course', "Cours")
            ->setColumns(6);

        yield AssociationField::new('teacher', "Idiomaster")
            ->setColumns(6);

        yield DateTimeField::new('start', "Début")
            ->setColumns(6);

        yield DateTimeField::new('end', "Fin")
            ->setColumns(6);

        yield AssociationField::new('participants', "Participants")
            ->autocomplete()
            ->setColumns(6);

    }

}
