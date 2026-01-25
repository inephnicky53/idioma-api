<?php

namespace App\Controller\Admin;

use App\Entity\News;
use App\Enum\NewsStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class NewsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return News::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Actualité')
            ->setEntityLabelInPlural('Actualités')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')
                ->setChoices([
                    'Brouillon' => NewsStatus::DRAFT->value,
                    'Publié' => NewsStatus::PUBLISHED->value,
                    'Archivé' => NewsStatus::ARCHIVED->value,
                ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        
        yield TextField::new('title', 'Titre')
            ->setRequired(true);
        
        yield TextField::new('excerpt', 'Extrait')
            ->setHelp('Court résumé de l\'actualité (max 500 caractères)')
            ->hideOnIndex();
        
        yield TextareaField::new('content', 'Contenu')
            ->setRequired(true)
            ->hideOnIndex();
        
        yield ImageField::new('image', 'Image')
            ->setBasePath('/uploads/news')
            ->setUploadDir('public/uploads/news')
            ->setUploadedFileNamePattern('[randomhash].[extension]')
            ->setRequired(false);
        
        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'Brouillon' => NewsStatus::DRAFT,
                'Publié' => NewsStatus::PUBLISHED,
                'Archivé' => NewsStatus::ARCHIVED,
            ])
            ->renderAsBadges([
                NewsStatus::DRAFT->value => 'warning',
                NewsStatus::PUBLISHED->value => 'success',
                NewsStatus::ARCHIVED->value => 'secondary',
            ]);
        
        yield DateTimeField::new('publishedAt', 'Date de publication')
            ->hideOnIndex();
        
        yield DateTimeField::new('createdAt', 'Créé le')
            ->onlyOnIndex();
    }
}