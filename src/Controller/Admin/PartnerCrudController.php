<?php

namespace App\Controller\Admin;

use App\Entity\Partner;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class PartnerCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Partner::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Partenaires')
            ->setEntityLabelInSingular('Partenaire')
            ->setDefaultSort(['position' => 'ASC', 'name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield ImageField::new('logoFile', 'Logo')
            ->setBasePath('/uploads/partners')
            ->setUploadDir('public/uploads/partners')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]');
        yield UrlField::new('website', 'Site web')->hideOnIndex();
        yield IntegerField::new('position', 'Ordre');
        yield ChoiceField::new('site', 'Site')
            ->setChoices([
                'Idioma & Straton' => Partner::SITE_BOTH,
                'Idioma' => Partner::SITE_IDIOMA,
                'Straton' => Partner::SITE_STRATON,
            ]);
        yield BooleanField::new('isActive', 'Actif');
    }
}
