<?php

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Enum\Currency;
use App\Trait\FrenchActionsTrait;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CourseCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

    public static function getEntityFqcn(): string
    {
        return Course::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Cours')
            ->setEntityLabelInPlural('Cours')
            ->setDefaultSort(['position' => 'ASC'])
            ->setSearchFields(['title', 'titleEn', 'description'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $this->configureFrenchActions($actions)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        // === INDEX ===
        if ($pageName === Crud::PAGE_INDEX) {
            yield IntegerField::new('position', 'Pos.');
            yield ImageField::new('thumbnail', 'Image')
                ->setBasePath('/uploads/courses/')
                ->setUploadDir('public/uploads/courses/');
            yield TextField::new('title', 'Titre');
            yield NumberField::new('price', 'Prix')
                ->setNumDecimals(2)
                ->formatValue(fn ($value, $entity) =>
                    number_format((float)$value, 2) . ' ' . $entity->getCurrency()->value
                );
            yield IntegerField::new('videoCount', 'Vidéos');
            yield BooleanField::new('isPublished', 'Publié');
            yield DateTimeField::new('createdAt', 'Créé le')
                ->setFormat('dd/MM/yyyy');
            return;
        }

        // === DETAIL ===
        if ($pageName === Crud::PAGE_DETAIL) {
            yield TextField::new('title', 'Titre (FR)');
            yield TextField::new('titleEn', 'Titre (EN)');
            yield TextareaField::new('description', 'Description (FR)');
            yield TextareaField::new('descriptionEn', 'Description (EN)');
            yield TextField::new('formattedPrice', 'Prix');
            yield ChoiceField::new('currency', 'Devise')
                ->setChoices(Currency::getChoices());
            yield ImageField::new('thumbnail', 'Image')
                ->setBasePath('/uploads/courses/');
            yield TextField::new('ebookPath', 'Chemin Ebook');
            yield TextField::new('ebookTitle', 'Titre Ebook');
            yield IntegerField::new('position', 'Position');
            yield BooleanField::new('isPublished', 'Publié');
            yield IntegerField::new('videoCount', 'Nombre de vidéos');
            yield AssociationField::new('videos', 'Vidéos');
            yield DateTimeField::new('createdAt', 'Créé le');
            yield DateTimeField::new('updatedAt', 'Modifié le');
            return;
        }

        // === NEW / EDIT ===
        yield TextField::new('title', 'Titre (FR)')
            ->setRequired(true)
            ->setHelp('Titre du cours en français');

        yield TextField::new('titleEn', 'Titre (EN)')
            ->setHelp('Titre du cours en anglais');

        yield TextareaField::new('description', 'Description (FR)')
            ->setHelp('Description détaillée en français')
            ->hideOnIndex();

        yield TextareaField::new('descriptionEn', 'Description (EN)')
            ->setHelp('Description détaillée en anglais')
            ->hideOnIndex();

        yield NumberField::new('price', 'Prix')
            ->setRequired(true)
            ->setNumDecimals(2)
            ->setHelp('Prix du cours');

        yield ChoiceField::new('currency', 'Devise')
            ->setChoices(Currency::getChoices())
            ->renderExpanded(false);

        yield ImageField::new('thumbnail', 'Image de couverture')
            ->setBasePath('/uploads/courses/')
            ->setUploadDir('public/uploads/courses/')
            ->setUploadedFileNamePattern('[randomhash].[extension]')
            ->setHelp('Image affichée dans la liste des cours');

        yield Field::new('ebookPath', 'Fichier Ebook (PDF)')
            ->setFormType(\EasyCorp\Bundle\EasyAdminBundle\Form\Type\FileUploadType::class)
            ->setFormTypeOptions([
                'upload_dir' => 'public/uploads/ebooks/',
                'upload_new' => fn ($file) => uniqid() . '.' . $file->guessExtension(),
            ])
            ->setRequired(false)
            ->setHelp('Fichier PDF du cours (max 50MB)');

        yield TextField::new('ebookTitle', 'Titre de l\'Ebook')
            ->setHelp('Titre affiché pour l\'ebook');

        yield IntegerField::new('position', 'Position')
            ->setHelp('Ordre d\'affichage (0 = premier)');

        yield BooleanField::new('isPublished', 'Publié')
            ->setHelp('Rendre le cours visible aux utilisateurs');
    }
}

