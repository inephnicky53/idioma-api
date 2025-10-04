<?php

namespace App\Controller\Admin\Teacher;

use App\Entity\SpokenLanguage;
use App\Idioma;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\LocaleField;

class SpokenLanguageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SpokenLanguage::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield AssociationField::new('teacher', "Idiomaster")
            ->autocomplete()
            ->setColumns(12);

        yield LocaleField::new('language', "Langue")
            ->showName()
            ->setColumns(12);

        yield ChoiceField::new('level', "Niveau")
            ->setChoices(Idioma::getLevelList())
            ->setColumns(21);
    }
}
