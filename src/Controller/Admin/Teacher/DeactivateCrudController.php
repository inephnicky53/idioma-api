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

class DeactivateCrudController extends AbstractTeacherCrudController
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
            ->setPageTitle(Crud::PAGE_INDEX, 'Professeurs Désactivés')
            ->setHelp(Crud::PAGE_INDEX, 'Liste des professeurs actuellement désactivés');
    }

    public function configureActions(Actions $actions): Actions
    {
        $reactivate = Action::new('reactivate', 'Réactiver')
            ->setIcon('fas fa-check-circle')
            ->setHtmlAttributes(['class' => 'btn btn-success'])
            ->linkToCrudAction('reactivateTeacher');

        $batchReactivate = Action::new('batchReactivate', 'Réactiver sélectionnés')
            ->linkToCrudAction('batchReactivateTeachers')
            ->addCssClass('btn btn-success')
            ->setIcon('fas fa-check-circle');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $reactivate)
            ->addBatchAction($batchReactivate)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        yield from $this->getCommonFields();
        yield from $this->addStatusFields();
    }

    public function reactivateTeacher(AdminContext $context): RedirectResponse
    {
        /** @var Teacher $teacher */
        $teacher = $context->getEntity()->getInstance();

        if ($this->statusService->activate($teacher)) {
            $this->addFlash('success', sprintf(
                'Le professeur "%s" a été réactivé avec succès.',
                $teacher->getUser()->getFullName()
            ));
        } else {
            $this->addFlash('error', 'Erreur lors de la réactivation du professeur.');
        }

        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    public function batchReactivateTeachers(AdminContext $context): RedirectResponse
    {
        $entityIds = $context->getRequest()->query->all('entityId');
        $reactivated = 0;

        foreach ($entityIds as $id) {
            $teacher = $this->repository->find($id);
            if ($teacher && $this->statusService->activate($teacher)) {
                $reactivated++;
            }
        }

        $this->addFlash('success', sprintf(
            '%d professeur(s) réactivé(s) avec succès.',
            $reactivated
        ));

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
            ->setParameter('isActive', false)
            ->leftJoin('entity.user', 'u')
            ->addSelect('u')
            ->orderBy('entity.activatedAt', 'DESC'); // Les plus récemment désactivés en premier
    }
}
