<?php

namespace App\Controller\Admin\Inbox;

use App\Controller\Admin\Teacher\SpokenLanguageCrudController;
use App\Controller\Admin\User\UserCrudController;
use App\Entity\Certification;
use App\Entity\InboxThread;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class InboxThreadCrudController extends AbstractCrudController
{
    public function __construct(private readonly string $teacherLabel = 'Idiomaster')
    {
    }

    public static function getEntityFqcn(): string
    {
        return InboxThread::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des discussions')
            ->setEntityLabelInPlural('Discussions')
            ->setEntityLabelInSingular(function (?InboxThread $thread, ?string $pageName) {
                $label = "la discussion ";
                if ($thread?->getTeacher()) $label .= "de {$thread->getTeacher()} ";
                if ($thread?->getCourse())
                    $label .= "du cours {$thread->getCourse()}";
                if ($thread && !$thread->getCourse() && $thread?->getParticipants())
                    $label .= "et {$thread->getParticipants()->first()}";
                return 'edit' === $pageName ? $label : 'la discussion';
            });
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN')//->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::DELETE, Action::EDIT])
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Détail')
            ->setHelp("Toutes les informations concernant la discussion");

        yield IdField::new('id')
            ->hideOnForm();

        yield AssociationField::new('teacher', $this->teacherLabel)
            ->autocomplete()
            ->setColumns(6);

        yield AssociationField::new('course', "Cours")
            ->autocomplete()
            ->setColumns(6);

        yield DateTimeField::new('createdAt', 'Date de création')
            ->onlyOnDetail();

        yield DateTimeField::new('updatedAt', 'Date de modification')
            ->onlyOnDetail();

        /*yield AssociationField::new('participants', "Participants")
            ->onlyOnIndex()
            ->setColumns(6);*/
        yield AssociationField::new('participants', "Participants")
            ->hideOnDetail()
            ->setTemplatePath('admin/field/inbox_participants.html.twig')
            ->setColumns(6);
        yield AssociationField::new('participants', "Participants")
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/inbox_participants_list.html.twig')
            ->setColumns(6);

        yield FormField::addTab('Messages')
            ->hideOnForm()
            ->setHelp("Tous les messages échangés");

        yield AssociationField::new('messages', "Messages")
            ->onlyOnIndex()
            ->setColumns(6);
        yield AssociationField::new('messages', "Messages")
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/inbox_messages.html.twig')
            ->setColumns(6);

        yield CollectionField::new('messages', "Messages")
            ->onlyOnForms()
            ->useEntryCrudForm(InboxMessageCrudController::class)
            ->setColumns(6);

        yield DateTimeField::new('createdAt', 'Date de création')
            ->onlyOnIndex();

    }
}
