<?php

namespace App\Controller\Admin;

use App\Entity\CourseLesson;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CourseLessonCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CourseLesson::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('title', 'Titre de la leçon')
            ->setColumns(8);

        yield ChoiceField::new('type', 'Type')
            ->setChoices([
                'Vidéo' => CourseLesson::TYPE_VIDEO,
                'Article' => CourseLesson::TYPE_ARTICLE,
                'Quiz' => CourseLesson::TYPE_QUIZ,
            ])
            ->setColumns(4);

        yield IntegerField::new('durationMinutes', 'Durée (minutes)')
            ->setColumns(4);

        yield IntegerField::new('position', 'Position')
            ->setColumns(4);

        yield BooleanField::new('isPreview', 'Aperçu gratuit')
            ->setHelp('Visible avant achat')
            ->setColumns(4);
    }
}
