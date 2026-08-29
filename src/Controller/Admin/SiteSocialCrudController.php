<?php

namespace App\Controller\Admin;

use App\Entity\SiteSocial;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class SiteSocialCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SiteSocial::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Réseaux sociaux')
            ->setEntityLabelInSingular('Réseau social')
            ->setDefaultSort(['position' => 'ASC', 'id' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield ChoiceField::new('type', 'Réseau')
            ->setChoices([
                'Facebook' => 'facebook',
                'X / Twitter' => 'twitter',
                'Instagram' => 'instagram',
                'LinkedIn' => 'linkedin',
                'YouTube' => 'youtube',
                'TikTok' => 'tiktok',
                'Site web' => 'website',
            ]);
        yield UrlField::new('link', 'URL');
        yield IntegerField::new('position', 'Ordre');
        yield ChoiceField::new('site', 'Site')
            ->setChoices([
                'Idioma & Straton' => SiteSocial::SITE_BOTH,
                'Idioma' => SiteSocial::SITE_IDIOMA,
                'Straton' => SiteSocial::SITE_STRATON,
            ]);
        yield BooleanField::new('isActive', 'Actif');
    }
}
