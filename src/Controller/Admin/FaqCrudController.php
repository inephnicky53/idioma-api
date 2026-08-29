<?php

namespace App\Controller\Admin;

use App\Entity\Faq;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class FaqCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Faq::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('FAQ')
            ->setEntityLabelInSingular('Question')
            ->setDefaultSort(['position' => 'ASC', 'id' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('question', 'Question');
        yield TextareaField::new('answer', 'Réponse')
            ->hideOnIndex()
            ->setNumOfRows(8);
        yield IntegerField::new('position', 'Ordre');
        yield ChoiceField::new('site', 'Site')
            ->setChoices([
                'Idioma & Straton' => Faq::SITE_BOTH,
                'Idioma' => Faq::SITE_IDIOMA,
                'Straton' => Faq::SITE_STRATON,
            ]);
        yield BooleanField::new('isActive', 'Actif');
    }
}
