<?php

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use App\Service\ContactReplyService;
use App\Trait\FrenchActionsTrait;
use DateTime;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ContactMessageCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminUrlGenerator $adminUrlGenerator,
        private ContactReplyService $contactReplyService
    ) {}

    public static function getEntityFqcn(): string
    {
        return ContactMessage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Message de contact')
            ->setEntityLabelInPlural('Messages de contact')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['name', 'email', 'subject', 'message'])
            ->showEntityActionsInlined();
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Par défaut, afficher les messages "new" en premier
        $hasStatusFilter = false;
        foreach ($filters as $filter) {
            if ($filter->getProperty() === 'status') {
                $hasStatusFilter = true;
                break;
            }
        }

        if (!$hasStatusFilter) {
            $qb->orderBy('CASE WHEN entity.status = :new THEN 0 ELSE 1 END', 'ASC')
               ->addOrderBy('entity.createdAt', 'DESC')
               ->setParameter('new', 'new');
        }

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')
                ->setChoices([
                    'Nouveau' => 'new',
                    'En cours' => 'in_progress',
                    'Répondu' => 'responded',
                    'Fermé' => 'closed',
                ]))
            ->add(TextFilter::new('service', 'Service'))
            ->add(DateTimeFilter::new('createdAt', 'Date de création'));
    }

    public function configureActions(Actions $actions): Actions
    {
        // Condition pour afficher les actions seulement si le message n'est pas répondu
        $isNotResponded = fn (ContactMessage $msg) => $msg->getStatus() !== 'responded' && $msg->getStatus() !== 'closed';
        $isNotClosed = fn (ContactMessage $msg) => $msg->getStatus() !== 'closed' && $msg->getStatus() !== 'responded';

        // Actions personnalisées
        $replyAction = Action::new('replySmtp', 'Répondre', 'fa fa-envelope')
            ->linkToCrudAction('replySmtp')
            ->setCssClass('btn btn-primary')
            ->displayIf($isNotClosed);

        $markAsResponded = Action::new('markAsResponded', 'Marquer comme répondu', 'fa fa-check-circle')
            ->linkToCrudAction('markAsResponded')
            ->setCssClass('btn btn-success')
            ->displayIf($isNotResponded);

        $markAsClosed = Action::new('markAsClosed', 'Fermer', 'fa fa-lock')
            ->linkToCrudAction('markAsClosed')
            ->setCssClass('btn btn-secondary')
            ->displayIf($isNotClosed);

        // Action DETAIL avec styles (n'existe pas par défaut)
        $detailAction = Action::new(Action::DETAIL, 'Voir', 'fa fa-eye')
            ->linkToCrudAction(Action::DETAIL)
            ->setCssClass('btn btn-info');

        return $this->configureFrenchActions($actions)
            // Page INDEX - Ajouter les actions qui n'existent pas
            ->add(Crud::PAGE_INDEX, $detailAction)
            ->add(Crud::PAGE_INDEX, $replyAction)
            // Page INDEX - Mettre à jour les actions existantes avec styles et icônes
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
            // Page DETAIL - Ajouter les actions personnalisées
            ->add(Crud::PAGE_DETAIL, $replyAction)
            ->add(Crud::PAGE_DETAIL, $markAsResponded)
            ->add(Crud::PAGE_DETAIL, $markAsClosed)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'replySmtp', Action::EDIT, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        $fieldsIndex = [
            TextField::new('name', 'Nom'),
            EmailField::new('email', 'Email'),
            TextField::new('subject', 'Sujet'),
            TextField::new('service', 'Service')
                ->formatValue(fn ($value) => $value ?: '—'),
            TextField::new('status', 'Statut')
                ->formatValue(fn ($value, ContactMessage $entity) => $this->renderStatusBadge($entity))
                ->hideOnForm(),
            DateTimeField::new('createdAt', 'Date')->setFormat('dd/MM/yy HH:mm'),
        ];

        $fieldsDetail = [
            FormField::addPanel('Informations du contact')->setIcon('fa fa-user'),
            TextField::new('name', 'Nom'),
            EmailField::new('email', 'Email'),
            TextField::new('phone', 'Téléphone')
                ->formatValue(fn ($value) => $value ?: '—'),
            TextField::new('service', 'Service')
                ->formatValue(fn ($value) => $value ?: '—'),

            FormField::addPanel('Message')->setIcon('fa fa-envelope'),
            TextField::new('subject', 'Sujet'),
            TextareaField::new('message', 'Message'),

            FormField::addPanel('Statut et Actions')->setIcon('fa fa-flag'),
            TextField::new('status', 'Statut')
                ->formatValue(fn ($value, ContactMessage $entity) => $this->renderStatusBadge($entity, true))
                ->hideOnForm(),
            DateTimeField::new('createdAt', 'Créé le'),
            DateTimeField::new('respondedAt', 'Répondu le')
                ->formatValue(fn ($value) => $value ? $value->format('dd/MM/yy HH:mm') : '—'),
            TextField::new('email', 'Répondre')
                ->formatValue(fn ($value, ContactMessage $entity) => sprintf(
                    '<a href="%s" class="btn btn-sm btn-primary" target="_blank"><i class="fa fa-reply me-1"></i>Répondre par email</a>',
                    $this->generateReplyMailtoLink($entity)
                ))
                ->hideOnForm(),
        ];

        $fieldsForm = [
            TextField::new('name', 'Nom'),
            EmailField::new('email', 'Email'),
            TextField::new('phone', 'Téléphone'),
            TextField::new('subject', 'Sujet'),
            TextField::new('service', 'Service'),
            TextareaField::new('message', 'Message'),
            ChoiceField::new('status', 'Statut')
                ->setChoices([
                    'Nouveau' => 'new',
                    'En cours' => 'in_progress',
                    'Répondu' => 'responded',
                    'Fermé' => 'closed',
                ]),
        ];

        return match ($pageName) {
            Crud::PAGE_INDEX => $fieldsIndex,
            Crud::PAGE_DETAIL => $fieldsDetail,
            default => $fieldsForm,
        };
    }

    public function replySmtp(AdminContext $context, Request $request): Response
    {
        $message = $this->getMessageFromContext($context, $request);
        if (!$message) {
            $this->addFlash('danger', 'Message introuvable.');
            return $this->redirectToIndex();
        }

        if ($request->isMethod('POST')) {
            $replyContent = $request->request->get('reply_content');

            if (!$replyContent || trim($replyContent) === '') {
                $this->addFlash('danger', 'Le contenu de la réponse ne peut pas être vide.');
                return $this->redirectToDetail($message);
            }

            try {
                $this->contactReplyService->sendReply($message, $replyContent);
                $this->addFlash('success', 'Réponse envoyée avec succès au contact.');
                // Rediriger vers la liste après succès
                return $this->redirectToIndex();
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur lors de l\'envoi de la réponse: ' . $e->getMessage());
                return $this->redirectToDetail($message);
            }
        }

        // Afficher le formulaire de réponse
        return $this->render('@EasyAdmin/crud/contact_reply_form.html.twig', [
            'message' => $message,
            'entityId' => $message->getId(),
        ]);
    }

    public function markAsResponded(AdminContext $context, Request $request): Response
    {
        $message = $this->getMessageFromContext($context, $request);
        if (!$message) {
            $this->addFlash('danger', 'Message introuvable.');
            return $this->redirectToIndex();
        }

        $message->setStatus('responded');
        $message->setRespondedAt(new DateTime());
        $this->entityManager->flush();

        $this->addFlash('success', 'Message marqué comme répondu.');
        return $this->redirectToDetail($message);
    }

    public function markAsClosed(AdminContext $context, Request $request): Response
    {
        $message = $this->getMessageFromContext($context, $request);
        if (!$message) {
            $this->addFlash('danger', 'Message introuvable.');
            return $this->redirectToIndex();
        }

        $message->setStatus('closed');
        $this->entityManager->flush();

        $this->addFlash('success', 'Message fermé.');
        return $this->redirectToDetail($message);
    }

    private function getMessageFromContext(AdminContext $context, Request $request): ?ContactMessage
    {
        $entityId = $request->query->get('entityId');
        if ($entityId) {
            return $this->entityManager->getRepository(ContactMessage::class)->find($entityId);
        }

        try {
            $entity = $context->getEntity();
            if ($entity && $entity->getInstance() instanceof ContactMessage) {
                return $entity->getInstance();
            }
        } catch (\TypeError $e) {
            // getEntity() peut retourner null
        }

        return null;
    }

    private function redirectToIndex(): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }

    private function redirectToDetail(ContactMessage $message): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($message->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

    private function renderStatusBadge(ContactMessage $message, bool $withDescription = false): string
    {
        $status = $message->getStatus();
        $statusMap = [
            'new' => ['label' => 'Nouveau', 'class' => 'danger', 'icon' => 'fa-star'],
            'in_progress' => ['label' => 'En cours', 'class' => 'warning', 'icon' => 'fa-hourglass-half'],
            'responded' => ['label' => 'Répondu', 'class' => 'info', 'icon' => 'fa-reply'],
            'closed' => ['label' => 'Fermé', 'class' => 'secondary', 'icon' => 'fa-check-circle'],
        ];

        $config = $statusMap[$status] ?? ['label' => $status, 'class' => 'secondary', 'icon' => 'fa-question'];

        return sprintf(
            '<span class="badge bg-%s text-white"><i class="fa %s me-1"></i>%s</span>',
            $config['class'],
            $config['icon'],
            $config['label']
        );
    }

    private function generateReplyMailtoLink(ContactMessage $message): string
    {
        $subject = 'Re: ' . $message->getSubject();
        $body = "Bonjour " . $message->getName() . ",\n\n";
        $body .= "Merci de nous avoir contacté.\n\n";
        $body .= "---\n";
        $body .= "Message original:\n";
        $body .= $message->getMessage() . "\n";

        return 'mailto:' . urlencode($message->getEmail())
            . '?subject=' . urlencode($subject)
            . '&body=' . urlencode($body);
    }
}

