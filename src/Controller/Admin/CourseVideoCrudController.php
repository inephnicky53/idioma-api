<?php

namespace App\Controller\Admin;

use App\Entity\CourseVideo;
use App\Trait\FrenchActionsTrait;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addJsFile('https://upload-widget.cloudinary.com/global/all.js')
            ->addJsFile('js/admin/cloudinary-upload.js');
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
            yield TextField::new('cloudinaryUrl', 'Statut')
                ->formatValue(function ($value) {
                    return $value 
                        ? '<span class="badge badge-success" title="Hébergé sur Cloudinary">☁️ Cloudinary</span>' 
                        : '<span class="badge badge-secondary" title="Stocké localement">🏠 Local</span>';
                })
                ->renderAsHtml();
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
            yield TextField::new('cloudinaryUrl', 'Statut Cloudinary')
                ->formatValue(function ($value) {
                    if ($value) {
                        return sprintf('<span class="badge badge-success">En ligne sur Cloudinary</span> <small class="text-muted">%s</small>', 
                            substr($value, 0, 30) . '...');
                    }
                    return '<span class="badge badge-warning">En attente ou local uniquement</span>';
                })
                ->renderAsHtml();
            yield TextField::new('streamUrl', 'Lien de streaming final')
                ->formatValue(function ($value) {
                    return $value ? sprintf('<a href="%s" target="_blank" class="text-primary"><i class="fa fa-external-link"></i> Tester le flux</a>', $value) : 'Non disponible';
                })
                ->renderAsHtml();
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

        yield TextField::new('cloudinaryUrl', 'URL de la vidéo (Cloudinary)')
            ->setHelp('L\'URL sera remplie automatiquement après l\'upload ou vous pouvez la coller ici.')
            ->setFormTypeOptions([
                'attr' => ['class' => 'cloudinary-url-field']
            ]);

        yield FormField::addPanel('Uploader vers Cloudinary')
            ->setIcon('fas fa-cloud-upload-alt')
            ->onlyOnForms()
            ->setHelp('
                <div class="cloudinary-upload-container">
                    <button type="button" id="cloudinary-upload-widget" class="btn btn-primary btn-lg">
                        <i class="fas fa-cloud-upload-alt"></i> Sélectionner une vidéo (Cloudinary)
                    </button>
                    <div id="upload-progress-container" style="display:none; margin-top: 15px;">
                        <div class="progress" style="height: 20px;">
                            <div id="upload-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                        </div>
                        <small id="upload-status-text" class="text-muted d-block mt-2">Préparation de l\'envoi...</small>
                    </div>
                </div>
            ');

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

