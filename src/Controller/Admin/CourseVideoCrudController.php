<?php

namespace App\Controller\Admin;

use App\Entity\CourseVideo;
use App\Trait\FrenchActionsTrait;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class CourseVideoCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

    public static function getEntityFqcn(): string
    {
        return CourseVideo::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Vidéo de cours')
            ->setEntityLabelInPlural('Vidéos de cours')
            ->setDefaultSort(['course' => 'ASC', 'position' => 'ASC'])
            ->setSearchFields(['title', 'titleEn', 'description', 'course.title'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $this->configureFrenchActions($actions)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('course', 'Cours'))
            ->add('isFreePreview');
    }

    public function configureFields(string $pageName): iterable
    {
        // === INDEX ===
        if ($pageName === Crud::PAGE_INDEX) {
            yield AssociationField::new('course', 'Cours');
            yield IntegerField::new('position', 'Pos.');
            yield TextField::new('title', 'Titre');
            yield TextField::new('formattedDuration', 'Durée');
            yield BooleanField::new('isFreePreview', 'Gratuit');
            yield DateTimeField::new('createdAt', 'Créé le')
                ->setFormat('dd/MM/yyyy');
            return;
        }

        // === DETAIL ===
        if ($pageName === Crud::PAGE_DETAIL) {
            yield AssociationField::new('course', 'Cours');
            yield TextField::new('title', 'Titre (FR)');
            yield TextField::new('titleEn', 'Titre (EN)');
            yield TextareaField::new('description', 'Description');
            yield TextField::new('videoFile', 'Fichier vidéo');
            yield TextField::new('streamUrl', 'URL de streaming');
            yield IntegerField::new('duration', 'Durée (secondes)');
            yield TextField::new('formattedDuration', 'Durée formatée');
            yield IntegerField::new('position', 'Position');
            yield ImageField::new('thumbnail', 'Miniature')
                ->setBasePath('/uploads/videos/');
            yield BooleanField::new('isFreePreview', 'Aperçu gratuit');
            yield DateTimeField::new('createdAt', 'Créé le');
            yield DateTimeField::new('updatedAt', 'Modifié le');
            return;
        }

        // === NEW / EDIT ===
        yield AssociationField::new('course', 'Cours')
            ->setRequired(true)
            ->setHelp('Cours auquel appartient cette vidéo');

        yield TextField::new('title', 'Titre (FR)')
            ->setRequired(true)
            ->setHelp('Titre de la vidéo en français');

        yield TextField::new('titleEn', 'Titre (EN)')
            ->setHelp('Titre de la vidéo en anglais');

        yield TextareaField::new('description', 'Description')
            ->setHelp('Description optionnelle de la vidéo')
            ->hideOnIndex();

        yield Field::new('videoFile', 'Fichier vidéo')
            ->setFormType(\EasyCorp\Bundle\EasyAdminBundle\Form\Type\FileUploadType::class)
            ->setFormTypeOptions([
                'upload_dir' => 'public/uploads/videos/',
                'upload_new' => fn ($file) => uniqid() . '.' . $file->guessExtension(),
            ])
            ->setRequired($pageName === Crud::PAGE_NEW)
            ->setHelp('Formats acceptés: MP4, WebM, MOV (max 500MB)');

        yield IntegerField::new('duration', 'Durée (secondes)')
            ->setHelp('Durée de la vidéo en secondes (ex: 300 pour 5 minutes)');

        yield IntegerField::new('position', 'Position')
            ->setHelp('Ordre d\'affichage dans le cours (0 = premier)');

        yield ImageField::new('thumbnail', 'Miniature')
            ->setBasePath('/uploads/videos/')
            ->setUploadDir('public/uploads/videos/')
            ->setUploadedFileNamePattern('[randomhash].[extension]')
            ->setHelp('Image miniature de la vidéo');

        yield BooleanField::new('isFreePreview', 'Aperçu gratuit')
            ->setHelp('Permettre la visualisation sans achat du cours');
    }
}

