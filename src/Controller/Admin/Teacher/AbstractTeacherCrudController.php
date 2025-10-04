<?php

namespace App\Controller\Admin\Teacher;

use App\Entity\Teacher;
use App\Repository\TeacherRepository;
use App\Service\Teacher\TeacherManager;
use App\Service\Teacher\TeacherStatusService;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimezoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Psr\Log\LoggerInterface;

abstract class AbstractTeacherCrudController extends AbstractCrudController
{
    public function __construct(
        protected readonly TeacherManager       $teacherManager,
        protected readonly TeacherRepository    $repository,
        protected readonly TeacherStatusService $statusService,
        protected readonly LoggerInterface      $logger
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return Teacher::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Idiomasters')
            ->setEntityLabelInSingular(function (?Teacher $teacher, ?string $pageName) {
                return $teacher;
            })
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(25)
            ->showEntityActionsInlined();
    }

    protected function getCommonFields(): iterable
    {
        // Informations générales
        yield FormField::addTab('Informations générales')
            ->setHelp("Les informations générales du idiomaster");

        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('user', "Utilisateur")
            ->autocomplete()
            ->setColumns(6)
            ->setQueryBuilder(fn($repository) => $repository->createQueryBuilder('u')
                ->where('u.isActive = true')
                ->orderBy('u.email', 'ASC')
            );
        

        yield AssociationField::new('language', "Langue principale")
            ->autocomplete()
            ->setColumns(6);

        yield CollectionField::new('spokenLanguages', "Langues parlées")
            ->useEntryCrudForm(SpokenLanguageCrudController::class)
            ->setColumns(12)
            ->setHelp('Langues que le idiomaster peut parler');

        yield CollectionField::new('teachingLanguages', "Langues enseignées")
            ->useEntryCrudForm(TeachingLanguageCrudController::class)
            ->setColumns(12)
            ->setHelp('Langues que le idiomaster peut enseigner');

        // Statuts et dates
        yield BooleanField::new('isActive', "Actif")
            ->renderAsSwitch(false);

        yield BooleanField::new('isVerified', "Vérifié")
            ->renderAsSwitch(false)
            ->onlyOnDetail();

        yield ChoiceField::new('status', 'Statut')
            ->setChoices(array_flip(Teacher::getStatusListForView()))
            ->renderAsBadges(Teacher::getStatusBadge())
            ->hideOnForm();

        // Dates importantes
        yield DateTimeField::new('createdAt', 'Créé le')
            ->onlyOnDetail()
            ->setFormat('dd/MM/yyyy HH:mm');

        yield DateTimeField::new('submitedAt', 'Soumis le')
            ->onlyOnDetail()
            ->setFormat('dd/MM/yyyy HH:mm');

        // Média
        yield FormField::addTab("Média")
            ->onlyOnForms()
            ->setHelp("Médias du idiomaster");

        yield UrlField::new('link', "Lien de présentation")
            ->hideOnIndex()
            ->setColumns(6)
            ->setHelp('Lien YouTube ou Vimeo');

        yield UrlField::new('video', "Vidéo de présentation")
            ->hideOnIndex()
            ->setColumns(6)
            ->setHelp('URL de la vidéo de présentation');

        yield UrlField::new('profile', "Photo de profil")
            ->hideOnIndex()
            ->setColumns(6)
            ->setHelp('URL de la photo de profil du idiomaster');

        // Description
        yield FormField::addTab('Présentation')
            ->setHelp("Informations de présentation du idiomaster");

        yield TextField::new('shortDescription', "Description courte")
            ->hideOnIndex()
            ->setMaxLength(255)
            ->setHelp('Résumé en une phrase');

        yield TextEditorField::new('description', "Présentation complète")
            ->setTemplatePath('admin/field/text_editor.html.twig')
            ->hideOnIndex()
            ->setNumOfRows(10);

        yield TextEditorField::new('experience', "Expérience")
            ->setTemplatePath('admin/field/text_editor.html.twig')
            ->hideOnIndex()
            ->setNumOfRows(8);

        yield TextEditorField::new('motivation', "Motivation")
            ->setTemplatePath('admin/field/text_editor.html.twig')
            ->hideOnIndex()
            ->setNumOfRows(6);

        // Certifications & Formations
        yield FormField::addTab("Certifications & Formations")
            ->onlyOnForms()
            ->setHelp("Certifications et formations du idiomaster");

        yield CollectionField::new('teacherCertifications', "Certifications")
            ->useEntryCrudForm(TeacherCertificationCrudController::class)
            ->hideOnIndex()
            ->setColumns(12)
            ->setHelp('Certifications et diplômes du idiomaster');

        yield CollectionField::new('teacherFormations', "Formations")
            ->useEntryCrudForm(TeacherFormationCrudController::class)
            ->hideOnIndex()
            ->setColumns(12)
            ->setHelp('Formations académiques du idiomaster');

        // Affichages détaillés (lecture seule)
        yield FormField::addPanel('Détails du Idiomaster')
            ->onlyOnDetail();

        // Médias (affichage)
        yield UrlField::new('profile', "Avatar (Cloudinary)")
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/url_image.html.twig');

        yield UrlField::new('video', "Vidéo de présentation")
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/url_video.html.twig');

        // Langues enseignées
        yield CollectionField::new('teachingLanguages', "Langues enseignées")
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/teaching_languages.html.twig');

        // Certifications & Formations (affichage)
        yield CollectionField::new('teacherCertifications', "Certifications")
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/teacher_certifications.html.twig');

        yield CollectionField::new('teacherFormations', "Formations")
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/teacher_formations.html.twig');

        // Tarification & Disponibilités
        yield FormField::addTab("Tarification & Disponibilités")
            ->setHelp("Configuration des prix et créneaux");

        yield MoneyField::new('price', "Prix/heure")
            ->setStoredAsCents(false)
            ->setCurrencyPropertyPath('currency')
            ->setColumns(6)
            ->setHelp('Prix en devise locale');

        yield AssociationField::new('currency', "Devise")
            ->onlyOnForms()
            ->setColumns(6);

        yield TimezoneField::new('timezone', 'Fuseau horaire')
            ->hideOnIndex()
            ->setColumns(6);

        yield CollectionField::new('disponibilities', "Créneaux disponibles")
            ->useEntryCrudForm(DisponibilityCrudController::class)
            ->hideOnIndex()
            ->setTemplatePath('admin/field/disponibility.html.twig')
            ->setColumns(12);

        // Statistiques (uniquement en détail)
        yield FormField::addTab('Statistiques')
            ->onlyOnDetail()
            ->setHelp("Statistiques et avis du idiomaster");

        yield AssociationField::new('courses', "Cours donnés")
            ->onlyOnIndex();

        yield AssociationField::new('ratings', "Avis reçus")
            ->onlyOnIndex();

        yield CollectionField::new('ratings', 'Évaluations')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/ratings.html.twig')
            ->setColumns(12);
    }

    protected function addStatusFields(): iterable
    {
        yield DateTimeField::new('activatedAt', "Activé le")
            ->onlyOnDetail()
            ->setFormat('dd/MM/yyyy HH:mm');

        yield AssociationField::new('activatedBy', "Activé par")
            ->onlyOnDetail();

        yield DateTimeField::new('verifiedAt', "Vérifié le")
            ->onlyOnDetail()
            ->setFormat('dd/MM/yyyy HH:mm');

        yield AssociationField::new('verifiedBy', "Vérifié par")
            ->onlyOnDetail();
    }
}