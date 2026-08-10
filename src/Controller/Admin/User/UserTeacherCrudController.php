<?php

namespace App\Controller\Admin\User;

use App\Entity\UserTeacher;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class UserTeacherCrudController extends AbstractCrudController
{
    public function __construct(private readonly string $teacherLabel = 'Idiomaster')
    {
    }

    public static function getEntityFqcn(): string
    {
        return UserTeacher::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, "Liste des {$this->teacherLabelLower()}s d'étudiants")
            ->setEntityLabelInPlural("{$this->teacherLabel}s d'étudiants")
            ->setEntityLabelInSingular(function (?UserTeacher $userTeacher, ?string $pageName) {
                return 'edit' === $pageName ? $userTeacher : "{$this->teacherLabel} d'étudiant";
            });
    }

    private function teacherLabelLower(): string
    {
        return lcfirst($this->teacherLabel);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield AssociationField::new('user', "Utilisateur")
            ->autocomplete()
            ->setColumns(6);

        yield AssociationField::new('teacher', $this->teacherLabel)
            ->autocomplete()
            ->setColumns(6);

        yield DateTimeField::new("createdAt", "Date d'ajout")
            ->hideOnForm()
            ->setColumns(6);
    }

}
