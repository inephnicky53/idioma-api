<?php

namespace App\Controller\Admin\Teacher;

use App\Entity\TeachingLanguage;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class TeachingLanguageCrudController extends AbstractCrudController
{
    public function __construct(private readonly string $teacherLabel = 'Idiomaster')
    {
    }

    public static function getEntityFqcn(): string
    {
        return TeachingLanguage::class;
    }

    public function configureFields(string $pageName): iterable
    {

        yield IdField::new('id')
            ->hideOnForm();

        yield AssociationField::new('teacher', $this->teacherLabel)
            ->autocomplete()
            ->setColumns(6);

        yield AssociationField::new('language', "Langue")
            ->autocomplete()
            ->setColumns(6);

        yield AssociationField::new('categories', "Catégories")
            ->autocomplete()
            ->setColumns(6);
    }
}
