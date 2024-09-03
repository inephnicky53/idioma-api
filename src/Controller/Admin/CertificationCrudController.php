<?php

namespace App\Controller\Admin;

use App\Entity\Certification;
use App\Entity\Promotion;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CertificationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Certification::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des certifications')
            ->setEntityLabelInPlural('Certifications')
            ->setEntityLabelInSingular(function (?Certification $certification, ?string $pageName) {
                return 'edit' === $pageName ? $certification : 'une certification';
            });
    }

    public function configureFields(string $pageName): iterable
    {

        yield IdField::new('id')->hideOnForm();

        yield TextField::new('name', "Titre du certificat")
            //->setColumns(6)
        ;
        yield AssociationField::new('languages', "Langues associées")
            //->hideOnIndex()
            //->setTemplatePath('admin/field/goals.html.twig')
            ->autocomplete()
            //->setColumns(6)
        ;
    }
}
