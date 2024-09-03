<?php

namespace App\Controller\Admin;

use App\Entity\Language;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\LocaleField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class LanguageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Language::class;
    }


    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des langues')
            ->setEntityLabelInPlural('Langues')
            ->setEntityLabelInSingular(function (?Language $language, ?string $pageName) {
                return 'edit' === $pageName ? $language : 'une langue';
            });
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->hideOnForm()
                ->setColumns(6),
            TextField::new('name', 'Nom')
                ->setColumns(6),
            CountryField::new('flag', "Drapeau")
                ->setColumns(6),
            LocaleField::new('locale', "Langue")
                ->setColumns(6),
            BooleanField::new('isActive', "Est active ?")
                ->setColumns(6),
            BooleanField::new('isPublic', "Est publique ?")
                ->setColumns(6),
        ];
    }
}
