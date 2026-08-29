<?php

namespace App\Controller\Admin;

use App\Entity\SiteContact;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SiteContactCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SiteContact::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Coordonnées')
            ->setEntityLabelInSingular('Coordonnées')
            ->setPageTitle(Crud::PAGE_INDEX, 'Coordonnées du site');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('phone', 'Téléphone');
        yield EmailField::new('email', 'E-mail');
        yield TextField::new('address', 'Adresse');
        yield ChoiceField::new('site', 'Site')
            ->setChoices([
                'Idioma & Straton' => SiteContact::SITE_BOTH,
                'Idioma' => SiteContact::SITE_IDIOMA,
                'Straton' => SiteContact::SITE_STRATON,
            ]);
        yield BooleanField::new('isActive', 'Actif');
    }
}
