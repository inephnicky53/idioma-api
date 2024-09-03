<?php

namespace App\Controller\Admin\Teacher;

use App\Entity\Teacher;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimezoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class DeactivateCrudController extends AbstractCrudController
{

    public static function getEntityFqcn(): string
    {
        return Teacher::class;
    }


    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, "Professeurs désactivés")
            ->setEntityLabelInPlural('Professeurs')
            ->setEntityLabelInSingular(function (?Teacher $teacher, ?string $pageName) {
                return 'edit' === $pageName ? $teacher : 'un professeur';
            });
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Informations générale')
            ->setHelp("Les informations générales du professeur");

        yield IdField::new('id')
            ->hideOnForm();

        yield AssociationField::new('user', "Utilisateur")
            ->autocomplete()
            ->setColumns(6);

        yield CollectionField::new('spokenLanguages', "Les langues parlées")
            ->useEntryCrudForm(SpokenLanguageCrudController::class)
            ->setColumns(12);

        yield CollectionField::new('teachingLanguages', "Les langues de formations")
            ->useEntryCrudForm(TeachingLanguageCrudController::class)
            ->setColumns(12);

        yield BooleanField::new('isActive', "Si actif");

        yield DateTimeField::new('activatedAt', "Date d'activation ou désactivation")
            ->onlyOnDetail();

        yield AssociationField::new('activatedBy', "Activé ou désactivé par")
            ->onlyOnDetail();

        yield BooleanField::new('isVerified', "Si vérifié")
            ->onlyOnDetail();

        yield DateTimeField::new('verifiedAt', "Date d'activation ou désactivation")
            ->onlyOnDetail();

        yield AssociationField::new('verifiedBy', "Activé ou désactivé par")
            ->onlyOnDetail();

        yield DateTimeField::new('createdAt', 'Date de création')
            ->onlyOnDetail();

        yield DateTimeField::new('updatedAt', 'Date de modification')
            ->onlyOnDetail();

        yield DateTimeField::new('submitedAt', 'Date de soumission')
            ->onlyOnDetail();


        yield FormField::addTab("Média")
            ->onlyOnForms()
            ->setHelp("Médias du professeur");

        /*yield VichField::new('video', 'Vidéo (si disponible)')
            //->useEntryCrudForm(AttachmentCrudController::class)
            //->setTemplatePath('field/media.html.twig')
            ->onlyOnForms()
            //->allowDelete(false)
            ->setColumns(6);*/

        yield UrlField::new('link', "Lien de présentation (Youtube ou Viméo)")
            ->hideOnIndex()
            ->setColumns(6);


        yield FormField::addTab('Description du professeur')
            ->setHelp("Les informations qui représentent le professeur");

        yield TextField::new('shortDescription', "Courte déscription")
            //->setTemplatePath('admin/field/text_editor.html.twig')
            ->hideOnIndex();

        yield TextEditorField::new('description', "Présentation")
            ->setTemplatePath('admin/field/text_editor.html.twig')
            ->hideOnIndex();

        yield TextEditorField::new('experience', "Expérience")
            ->setTemplatePath('admin/field/text_editor.html.twig')
            ->hideOnIndex();

        yield TextEditorField::new('motivation', "Motivation pour les élèves")
            ->setTemplatePath('admin/field/text_editor.html.twig')
            ->hideOnIndex();


        yield FormField::addTab("Disponibilités et prix de l'heure")
            ->setHelp("Les informations de disponibilités du professeur");

        yield MoneyField::new('price', "Prix par heure")
            ->setStoredAsCents(false)
            ->setCurrencyPropertyPath('currency')
            ->setColumns(6);

        yield AssociationField::new('currency', "Dévise de facturation")
            ->onlyOnForms()
            ->setColumns(6);

        yield TimezoneField::new('timezone')
            ->hideOnIndex()
            ->setColumns(6);

        yield CollectionField::new('disponibilities', "Disponibilités")
            ->useEntryCrudForm(DisponibilityCrudController::class)
            ->hideOnIndex()
            ->setTemplatePath('admin/field/disponibility.html.twig')
            ->setColumns(12);

        yield CollectionField::new('plannings', "Plannings")
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/plannings.html.twig')
            ->setColumns(6);


        yield FormField::addTab('Avis des utilisateurs')
            ->onlyOnDetail()
            ->setHelp("Les avis des utilisateurs concernant le professeur");

        yield AssociationField::new('courses', "Cours")
            ->onlyOnIndex();

        yield AssociationField::new('ratings', "Avis")
            ->onlyOnIndex();

        yield CollectionField::new('ratings')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/ratings.html.twig')
            ->setColumns(6);

        yield ChoiceField::new('status', 'Status')
            ->setChoices(array_flip(Teacher::getStatusListForView()))
            ->renderAsBadges(Teacher::getStatusBadge())
            ->hideOnForm();
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.isActive = :isActive')
            ->setParameter('isActive', false);
    }
}
