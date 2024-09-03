<?php

namespace App\Controller\Admin\User;

use App\Entity\UserCourse;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class UserCourseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserCourse::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des cours des étudiants')
            ->setEntityLabelInPlural('Cours des étudiants')
            ->setEntityLabelInSingular(function (?UserCourse $userCourse, ?string $pageName) {
                return 'edit' === $pageName ? $userCourse : "un cours de l'étudiant";
            });
    }

    public function configureFields(string $pageName): iterable
    {

        yield IdField::new('id')
            ->hideOnForm();

        yield AssociationField::new('user', "Utilisateur")
            ->autocomplete()
            ->setColumns(6);

        yield AssociationField::new('course', "Cours")
            ->autocomplete()
            ->setColumns(6);

        yield DateTimeField::new('addedAt', "Date d'ajout")
            ->hideOnForm()
            ->setColumns(6);

        yield DateTimeField::new('startedAt', "Date de début")
            ->onlyOnDetail()
            ->setColumns(6);

        yield DateTimeField::new('endAt', "Date de fin")
            ->onlyOnDetail()
            ->setColumns(6);

        yield IntegerField::new('records', "Temps de cours suivi")
            ->onlyOnDetail()
            ->setColumns(6);

        yield BooleanField::new('isBuyed', "Si achêté")
            //->onlyOnDetail()
            ->setColumns(6);

        yield DateTimeField::new('buyedAt', "Date d'achat")
            //->onlyOnDetail()
            ->setColumns(6);
    }

}
