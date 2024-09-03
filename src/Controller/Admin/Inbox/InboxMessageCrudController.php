<?php

namespace App\Controller\Admin\Inbox;

use App\Entity\InboxMessage;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class InboxMessageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return InboxMessage::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield AssociationField::new('author', "Auteur")
            ->autocomplete()
            ->setColumns(6);

        yield AssociationField::new('tagMessage', "Message tagué")
            ->autocomplete()
            ->setColumns(6);

        yield TextareaField::new('body', "Contenu");
    }
}
