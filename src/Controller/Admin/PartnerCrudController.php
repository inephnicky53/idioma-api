<?php

namespace App\Controller\Admin;

use App\Entity\Partner;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Vich\UploaderBundle\Form\Type\VichImageType;

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
        yield ImageField::new('logoName', 'Logo')
            ->setBasePath('/uploads/partners')
            ->onlyOnIndex();
        yield Field::new('logoFile', 'Logo')
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions([
                'allow_delete' => false,
                'download_uri' => false,
                'required' => $pageName === Crud::PAGE_NEW,
            ])
            ->onlyOnForms();
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
