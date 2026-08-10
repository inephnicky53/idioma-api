<?php

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Field\VichField;
use App\Form\AttachmentType;
use App\Idioma;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CurrencyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CourseCrudController extends AbstractCrudController
{
    public function __construct(private readonly string $teacherLabel = 'Idiomaster')
    {
    }

    public static function getEntityFqcn(): string
    {
        return Course::class;
    }



    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des cours')
            ->setEntityLabelInPlural('Cours')
            ->setEntityLabelInSingular(function (?Course $course, ?string $pageName) {
                return 'edit' === $pageName ? $course : 'un cours';
            });
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }


    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            $imageField = CollectionField::new('thumbnail', 'Image du cours')
                //->setEntryType(AttachmentType::class)
                ->useEntryCrudForm(AttachmentCrudController::class);
            //$imageField = VichField::new('file', 'Image du cours')->onlyOnForms();
        } else {
            $imageField = CollectionField::new('thumbnail', 'Image du cours')
                //->setBasePath('uploads/user/images')
                //->setUploadDir('uploads/user/images')
                //->onlyOnForms()
                //->setFormType(VichImageType::class)
            ;
        }

        yield IdField::new('id')
            ->hideOnForm();
        yield TextField::new('title')
            ->setColumns(6);
        yield AssociationField::new('language', "Langue")
            ->autocomplete()
            ->setColumns(6);
        yield AssociationField::new('teacher', $this->teacherLabel)
            ->autocomplete()
            ->setColumns(6);
        yield AssociationField::new('categories')
            ->hideOnIndex()
            ->setColumns(6);
        yield TextField::new('difficulty')
            ->hideOnIndex()
            ->setColumns(6);
        yield TextEditorField::new('description')
            ->hideOnIndex();
        yield TextField::new('status')
            ->hideOnIndex()
            ->setColumns(6);
        yield ChoiceField::new('difficulty', "Difficulté")
            ->renderAsBadges(array_flip(Course::getDifficultiesBadge()))
            ->setChoices(array_flip(Course::getDifficulties()))
            //->hideOnIndex()
            ->setColumns(6);

        yield IntegerField::new('duration', "Durée (heures)")
            ->setColumns(6);
        //yield $imageField;

        yield CollectionField::new('thumbnails', 'Image du cours')
            ->useEntryCrudForm(AttachmentCrudController::class)
            ->setTemplatePath('admin/field/media.html.twig')
            //->hideOnIndex()
            ->allowDelete(false)
            ->setColumns(6);

        /*yield BooleanField::new('isPaid', "Payant")
            ->setColumns(6);*/

        yield IntegerField::new('amount', "Montant")
            ->onlyOnForms()
            ->setColumns(6);

        yield MoneyField::new('amount', "Montant")
            ->hideOnForm()
            ->setCurrencyPropertyPath('currency')
            ->setColumns(6)
            ->setCustomOption('storedAsCents', false);

        yield BooleanField::new('isPromote', "Promo")
            //->hideOnIndex()
            ->setColumns(6);

        yield MoneyField::new('amountPromo', "Reduction")
            //->hideOnIndex()
            ->setCurrencyPropertyPath('currency')
            ->setCustomOption('storedAsCents', false)
            ->setColumns(6);

        yield AssociationField::new('currency', "Dévise")
            ->onlyOnForms()
            ->setColumns(6);
    }

}
