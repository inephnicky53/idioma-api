<?php

namespace App\Controller\Admin;

use App\Entity\Contact;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ContactCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Contact::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', '#')->hideOnForm();
        yield TextField::new('lastname', 'Nom');
        yield TextField::new('firstname', 'Prénom');
        yield EmailField::new('email', 'Email');
        yield TextField::new('phone', 'Phone');
        yield TextField::new('title', 'Titre');
        yield TextEditorField::new('content', 'Description');
    }
}
