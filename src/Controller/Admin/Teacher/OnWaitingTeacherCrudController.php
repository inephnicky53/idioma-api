<?php

namespace App\Controller\Admin\Teacher;

use App\Entity\Teacher;
use App\Repository\TeacherRepository;
use App\Service\Teacher\TeacherManager;
use App\Service\Teacher\TeacherStatusService;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class OnWaitingTeacherCrudController extends AbstractTeacherCrudController
{
    public function __construct(
        TeacherManager                     $teacherManager,
        TeacherRepository                  $repository,
        TeacherStatusService               $statusService,
        LoggerInterface                    $logger,
        private readonly AdminUrlGenerator $adminUrlGenerator
    )
    {
        parent::__construct($teacherManager, $repository, $statusService, $logger);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setPageTitle(Crud::PAGE_INDEX, "Professeurs en Attente")
            ->setHelp(Crud::PAGE_INDEX, 'Professeurs en attente de validation par l\'équipe');
    }

    public function configureActions(Actions $actions): Actions
    {
        $validate = Action::new('validate', 'Valider')
            ->setIcon('fas fa-check')
            ->setHtmlAttributes(['class' => 'btn btn-success'])
            ->linkToCrudAction('validateTeacher');

        $reject = Action::new('reject', 'Rejeter')
            ->setIcon('fas fa-times')
            ->setHtmlAttributes(['class' => 'btn btn-danger'])
            ->linkToCrudAction('rejectTeacher');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $validate)
            ->add(Crud::PAGE_INDEX, $reject)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        yield from $this->getCommonFields();
        yield from $this->addStatusFields();
    }

    public function validateTeacher(AdminContext $context): RedirectResponse
    {
        /** @var Teacher $teacher */
        $teacher = $context->getEntity()->getInstance();

        if ($this->statusService->activate($teacher) && $this->statusService->verify($teacher)) {
            $this->addFlash('success', sprintf(
                'Le professeur "%s" a été validé et activé.',
                $teacher->getUser()->getFullName()
            ));
        } else {
            $this->addFlash('error', 'Erreur lors de la validation du professeur.');
        }

        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    public function rejectTeacher(AdminContext $context): RedirectResponse
    {
        /** @var Teacher $teacher */
        $teacher = $context->getEntity()->getInstance();

        if ($this->statusService->deactivate($teacher)) {
            $this->addFlash('warning', sprintf(
                'Le professeur "%s" a été rejeté.',
                $teacher->getUser()->getFullName()
            ));
        } else {
            $this->addFlash('error', 'Erreur lors du rejet du professeur.');
        }

        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    public function createIndexQueryBuilder(
        SearchDto        $searchDto,
        EntityDto        $entityDto,
        FieldCollection  $fields,
        FilterCollection $filters
    ): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.isActive = :isActive')
            ->andWhere('entity.isVerified = :isVerified')
            ->setParameter('isActive', false)
            ->setParameter('isVerified', false)
            ->leftJoin('entity.user', 'u')
            ->addSelect('u')
            ->orderBy('entity.submitedAt', 'ASC'); // Les plus anciens en premier
    }
}
