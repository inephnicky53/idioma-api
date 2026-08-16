<?php

namespace App\Controller\Admin;

use App\Entity\CourseSection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CourseSectionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CourseSection::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('title', 'Titre du module')
            ->setColumns(8);

        yield IntegerField::new('position', 'Position')
            ->setColumns(4);

        yield CollectionField::new('lessons', 'Leçons')
            ->useEntryCrudForm(CourseLessonCrudController::class)
            ->setColumns(12);
    }
}
