<?php

namespace App\Controller\Admin;

use App\Entity\Attachment;
use App\Entity\Promotion;
use App\Field\VichField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;

class AttachmentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Attachment::class;
    }
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des attachements')
            ->setEntityLabelInPlural('Attachements')
            ->setEntityLabelInSingular(function (?Attachment $attachement, ?string $pageName) {
                return 'edit' === $pageName ? $attachement : 'un attachement';
            });
    }


    public function configureFields(string $pageName): iterable
    {

        yield VichField::new('file', false)->onlyOnForms();
    }

}
