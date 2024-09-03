<?php

namespace App\Controller\Admin;

use App\Aggregat\Translation;
use App\Field\VichField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class TranslationCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;

    public function __construct(AdminUrlGenerator $adminUrlGenerator)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
    }
    public static function getEntityFqcn(): string
    {
        return Translation::class;
    }
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des traductions')
            ->setEntityLabelInPlural('Traductions')
            ->setEntityLabelInSingular(function (?Translation $attachement, ?string $pageName) {
                return 'edit' === $pageName ? $attachement : 'une traduction';
            })

            ->overrideTemplate('crud/layout', 'admin/advanced_layout.html.twig')

            ->overrideTemplates([
                'crud' => 'admin/react_content.html.twig',
            ])
            ;
    }


    public function configureFields(string $pageName): iterable
    {

        yield VichField::new('file', false)->onlyOnForms();
    }

}
