<?php

namespace App\Controller\Admin;

use App\Entity\TranslationRequest;
use App\Enum\TranslationStatus;
use App\Manager\TranslationRequestManager;
use App\Trait\FrenchActionsTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class TranslationRequestCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminUrlGenerator $adminUrlGenerator,
        private TranslationRequestManager $translationRequestManager
    ) {}

    public static function getEntityFqcn(): string
    {
        return TranslationRequest::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Demande de traduction')
            ->setEntityLabelInPlural('Demandes de traduction')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['name', 'email', 'phone', 'documentType', 'message'])
            ->showEntityActionsInlined()
            ->setPageTitle('index', 'Demandes de traduction')
            ->setPageTitle('new', 'Nouvelle demande')
            ->setPageTitle('edit', 'Modifier la demande')
            ->setPageTitle('detail', 'Détails de la demande');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Par défaut, afficher les demandes "pending" en premier
        $hasStatusFilter = false;
        foreach ($filters as $filter) {
            if ($filter->getProperty() === 'status') {
                $hasStatusFilter = true;
                break;
            }
        }

        if (!$hasStatusFilter) {
            $qb->orderBy('CASE 
                WHEN entity.status = :pending THEN 0 
                WHEN entity.status = :in_progress THEN 1 
                ELSE 2 
                END', 'ASC')
               ->addOrderBy('entity.createdAt', 'DESC')
               ->setParameter('pending', TranslationStatus::PENDING)
               ->setParameter('in_progress', TranslationStatus::IN_PROGRESS);
        }

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')
                ->setChoices([
                    'En attente' => TranslationStatus::PENDING->value,
                    'En cours' => TranslationStatus::IN_PROGRESS->value,
                    'Terminé' => TranslationStatus::COMPLETED->value,
                    'Annulé' => TranslationStatus::CANCELLED->value,
                ]))
            ->add(TextFilter::new('documentType', 'Type de document'))
            ->add(TextFilter::new('sourceLanguage', 'Langue source'))
            ->add(TextFilter::new('targetLanguage', 'Langue cible'))
            ->add(DateTimeFilter::new('createdAt', 'Date de création'))
            ->add(DateTimeFilter::new('deadline', 'Date limite'));
    }

    public function configureActions(Actions $actions): Actions
    {
        // Actions personnalisées
        $markInProgress = Action::new('markInProgress', 'Marquer en cours', 'fa fa-play')
            ->linkToCrudAction('markInProgress')
            ->setCssClass('btn btn-info')
            ->displayIf(fn (TranslationRequest $request) => 
                $request->getStatus() === TranslationStatus::PENDING
            );

        $markCompleted = Action::new('markCompleted', 'Marquer terminé', 'fa fa-check-circle')
            ->linkToCrudAction('markCompleted')
            ->setCssClass('btn btn-success')
            ->displayIf(fn (TranslationRequest $request) => 
                $request->getStatus() === TranslationStatus::IN_PROGRESS
            );

        $markCancelled = Action::new('markCancelled', 'Annuler', 'fa fa-times-circle')
            ->linkToCrudAction('markCancelled')
            ->setCssClass('btn btn-warning')
            ->displayIf(fn (TranslationRequest $request) => 
                $request->getStatus() !== TranslationStatus::COMPLETED && 
                $request->getStatus() !== TranslationStatus::CANCELLED
            );

        $contactClient = Action::new('contactClient', 'Contacter', 'fa fa-envelope')
            ->linkToCrudAction('contactClient')
            ->setCssClass('btn btn-primary');

        return $this->configureFrenchActions($actions)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $markInProgress)
            ->add(Crud::PAGE_DETAIL, $markInProgress)
            ->add(Crud::PAGE_DETAIL, $markCompleted)
            ->add(Crud::PAGE_DETAIL, $markCancelled)
            ->add(Crud::PAGE_DETAIL, $contactClient)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => $action
                ->setIcon('fa fa-edit')
                ->setLabel('Modifier')
                ->setCssClass('btn btn-warning')
            )
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => $action
                ->setIcon('fa fa-trash')
                ->setLabel('Supprimer')
                ->setCssClass('btn btn-danger text-white')
            )
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'markInProgress', Action::EDIT, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        // === INDEX ===
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('name', 'Nom');
            yield EmailField::new('email', 'Email');
            yield TextField::new('documentType', 'Type de document');
            yield TextField::new('sourceLanguage', 'De')
                ->formatValue(fn ($value) => strtoupper($value));
            yield TextField::new('targetLanguage', 'Vers')
                ->formatValue(fn ($value) => strtoupper($value));
            yield ChoiceField::new('status', 'Statut')
                ->setChoices([
                    'En attente' => TranslationStatus::PENDING->value,
                    'En cours' => TranslationStatus::IN_PROGRESS->value,
                    'Terminé' => TranslationStatus::COMPLETED->value,
                    'Annulé' => TranslationStatus::CANCELLED->value,
                ])
                ->formatValue(fn ($value, TranslationRequest $entity) => $this->renderStatusBadge($entity))
                ->hideOnForm();
            yield DateTimeField::new('createdAt', 'Date')
                ->setFormat('dd/MM/yy HH:mm');
            return;
        }

        // === DETAIL ===
        if ($pageName === Crud::PAGE_DETAIL) {
            yield FormField::addPanel('Informations du client')->setIcon('fa fa-user');
            yield TextField::new('name', 'Nom');
            yield EmailField::new('email', 'Email');
            yield TextField::new('phone', 'Téléphone')
                ->formatValue(fn ($value) => $value ?: '—');

            yield FormField::addPanel('Détails de la traduction')->setIcon('fa fa-language');
            yield TextField::new('documentType', 'Type de document');
            yield TextField::new('sourceLanguage', 'Langue source')
                ->formatValue(fn ($value) => strtoupper($value));
            yield TextField::new('targetLanguage', 'Langue cible')
                ->formatValue(fn ($value) => strtoupper($value));
            yield DateField::new('deadline', 'Date limite')
                ->formatValue(fn ($value) => $value ? $value->format('d/m/Y') : '—');
            yield TextareaField::new('message', 'Message')
                ->formatValue(fn ($value) => $value ?: '—');

            yield FormField::addPanel('Statut et suivi')->setIcon('fa fa-flag');
            yield ChoiceField::new('status', 'Statut')
                ->setChoices([
                    'En attente' => TranslationStatus::PENDING->value,
                    'En cours' => TranslationStatus::IN_PROGRESS->value,
                    'Terminé' => TranslationStatus::COMPLETED->value,
                    'Annulé' => TranslationStatus::CANCELLED->value,
                ])
                ->formatValue(fn ($value, TranslationRequest $entity) => $this->renderStatusBadge($entity, true))
                ->hideOnForm();
            yield DateTimeField::new('createdAt', 'Créé le')
                ->setFormat('dd/MM/yyyy HH:mm');
            yield DateTimeField::new('updatedAt', 'Modifié le')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->formatValue(fn ($value) => $value ? $value->format('d/m/Y H:i') : '—');

            yield FormField::addPanel('Actions rapides')->setIcon('fa fa-bolt');
            yield TextField::new('email', 'Contact')
                ->formatValue(fn ($value, TranslationRequest $entity) => sprintf(
                    '<a href="mailto:%s?subject=Demande de traduction - %s" class="btn btn-sm btn-primary" target="_blank">
                        <i class="fa fa-envelope me-1"></i>Envoyer un email
                    </a>',
                    $entity->getEmail(),
                    urlencode($entity->getDocumentType())
                ))
                ->hideOnForm();
            return;
        }

        // === NEW / EDIT ===
        yield FormField::addPanel('Informations du client');
        yield TextField::new('name', 'Nom')
            ->setRequired(true);
        yield EmailField::new('email', 'Email')
            ->setRequired(true);
        yield TextField::new('phone', 'Téléphone');

        yield FormField::addPanel('Détails de la traduction');
        yield TextField::new('documentType', 'Type de document')
            ->setRequired(true)
            ->setHelp('Ex: Contrat, Certificat, Document légal, etc.');
        yield ChoiceField::new('sourceLanguage', 'Langue source')
            ->setChoices([
                'Français' => 'fr',
                'Anglais' => 'en',
                'Espagnol' => 'es',
                'Lingala' => 'ln',
                'Swahili' => 'sw',
                'Kikongo' => 'kg',
                'Tshiluba' => 'lu',
            ])
            ->setRequired(true);
        yield ChoiceField::new('targetLanguage', 'Langue cible')
            ->setChoices([
                'Français' => 'fr',
                'Anglais' => 'en',
                'Espagnol' => 'es',
                'Lingala' => 'ln',
                'Swahili' => 'sw',
                'Kikongo' => 'kg',
                'Tshiluba' => 'lu',
            ])
            ->setRequired(true);
        yield DateField::new('deadline', 'Date limite');
        yield TextareaField::new('message', 'Message')
            ->setHelp('Informations complémentaires');

        yield FormField::addPanel('Statut');
        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'En attente' => TranslationStatus::PENDING->value,
                'En cours' => TranslationStatus::IN_PROGRESS->value,
                'Terminé' => TranslationStatus::COMPLETED->value,
                'Annulé' => TranslationStatus::CANCELLED->value,
            ])
            ->setRequired(true);
    }

    // Actions personnalisées

    public function markInProgress(AdminContext $context): Response
    {
        $request = $context->getEntity()->getInstance();
        
        if (!$request instanceof TranslationRequest) {
            $this->addFlash('danger', 'Demande introuvable.');
            return $this->redirectToIndex();
        }

        $this->translationRequestManager->markInProgress($request);
        $this->addFlash('success', 'Demande marquée comme en cours.');

        return $this->redirectToDetail($request);
    }

    public function markCompleted(AdminContext $context): Response
    {
        $request = $context->getEntity()->getInstance();
        
        if (!$request instanceof TranslationRequest) {
            $this->addFlash('danger', 'Demande introuvable.');
            return $this->redirectToIndex();
        }

        $this->translationRequestManager->markCompleted($request);
        $this->addFlash('success', 'Demande marquée comme terminée.');

        return $this->redirectToDetail($request);
    }

    public function markCancelled(AdminContext $context): Response
    {
        $request = $context->getEntity()->getInstance();
        
        if (!$request instanceof TranslationRequest) {
            $this->addFlash('danger', 'Demande introuvable.');
            return $this->redirectToIndex();
        }

        $this->translationRequestManager->cancel($request);
        $this->addFlash('success', 'Demande annulée.');

        return $this->redirectToDetail($request);
    }

    public function contactClient(AdminContext $context): Response
    {
        $request = $context->getEntity()->getInstance();
        
        if (!$request instanceof TranslationRequest) {
            $this->addFlash('danger', 'Demande introuvable.');
            return $this->redirectToIndex();
        }

        $subject = 'Demande de traduction - ' . $request->getDocumentType();
        $mailtoLink = sprintf(
            'mailto:%s?subject=%s',
            $request->getEmail(),
            urlencode($subject)
        );

        return $this->redirect($mailtoLink);
    }

    // Méthodes utilitaires

    private function renderStatusBadge(TranslationRequest $request, bool $large = false): string
    {
        $status = $request->getStatus();
        $sizeClass = $large ? 'badge-lg' : '';
        
        $badges = [
            TranslationStatus::PENDING->value => '<span class="badge bg-warning text-dark ' . $sizeClass . '"><i class="fa fa-clock me-1"></i>En attente</span>',
            TranslationStatus::IN_PROGRESS->value => '<span class="badge bg-info ' . $sizeClass . '"><i class="fa fa-play me-1"></i>En cours</span>',
            TranslationStatus::COMPLETED->value => '<span class="badge bg-success ' . $sizeClass . '"><i class="fa fa-check-circle me-1"></i>Terminé</span>',
            TranslationStatus::CANCELLED->value => '<span class="badge bg-secondary ' . $sizeClass . '"><i class="fa fa-times-circle me-1"></i>Annulé</span>',
        ];

        return $badges[$status->value] ?? '<span class="badge bg-secondary">Inconnu</span>';
    }

    private function redirectToIndex(): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }

    private function redirectToDetail(TranslationRequest $request): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($request->getId())
            ->generateUrl();

        return $this->redirect($url);
    }
}