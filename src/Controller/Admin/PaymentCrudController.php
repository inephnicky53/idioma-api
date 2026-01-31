<?php

namespace App\Controller\Admin;

use App\Entity\CoursePurchase;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Enum\PurchaseType;
use App\Service\Payment\PaymentManager;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminUrlGenerator $adminUrlGenerator,
        private PaymentManager $paymentManager
    ) {}

    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Paiement')
            ->setEntityLabelInPlural('Paiements')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['user.email', 'user.firstName', 'user.lastName', 'transactionId', 'phone', 'reference'])
            ->showEntityActionsInlined();
    }

    /**
     * Exclure par défaut les paiements en "Initialisation" sauf si filtre appliqué
     */
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Si aucun filtre sur le statut n'est appliqué, exclure les INIT
        $hasStatusFilter = false;
        foreach ($filters as $filter) {
            if ($filter->getProperty() === 'status') {
                $hasStatusFilter = true;
                break;
            }
        }

        if (!$hasStatusFilter) {
            $qb->andWhere('entity.status != :initStatus')
               ->setParameter('initStatus', PaymentStatus::INIT);
        }

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('purchaseType', 'Type d\'achat')
                ->setChoices(PurchaseType::getChoices()))
            ->add(ChoiceFilter::new('status', 'Statut')
                ->setChoices(PaymentStatus::getChoices()))
            ->add(ChoiceFilter::new('paymentMethod', 'Méthode')
                ->setChoices(PaymentMethod::getChoices()))
            ->add(EntityFilter::new('user', 'Utilisateur'))
            ->add(EntityFilter::new('subscriptionPlan', 'Plan'))
            ->add(EntityFilter::new('course', 'Cours'))
            ->add(DateTimeFilter::new('createdAt', 'Date de création'));
    }

    public function configureActions(Actions $actions): Actions
    {
        // Action personnalisée pour valider un paiement cash
        $validateCashPayment = Action::new('validateCashPayment', 'Valider', 'fa fa-check-circle')
            ->linkToCrudAction('validateCashPayment')
            ->setCssClass('btn btn-success')
            ->displayIf(fn (Payment $payment) =>
                $payment->getPaymentMethod() === PaymentMethod::CASH &&
                !$payment->getStatus()->isFinal()
            );

        // Action pour rejeter un paiement
        $rejectPayment = Action::new('rejectPayment', 'Rejeter', 'fa fa-times-circle')
            ->linkToCrudAction('rejectPayment')
            ->setCssClass('btn btn-danger')
            ->displayIf(fn (Payment $payment) => !$payment->getStatus()->isFinal());

        return $this->configureFrenchActions($actions)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $validateCashPayment)
            ->add(Crud::PAGE_DETAIL, $rejectPayment)
            ->add(Crud::PAGE_INDEX, $validateCashPayment)
            ->disable(Action::DELETE) // Les paiements ne doivent pas être supprimés (raisons légales/comptables)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'validateCashPayment', Action::EDIT]);
    }

    public function configureFields(string $pageName): iterable
    {
        // === Champs pour INDEX ===
        $fieldsIndex = [
            AssociationField::new('user')->setLabel('Utilisateur'),
            TextField::new('purchaseTypeLabel', 'Type')
                ->formatValue(fn ($value, Payment $entity) => $this->renderPurchaseTypeBadge($entity)),
            TextField::new('product', 'Produit'),
            NumberField::new('amount')->setLabel('Montant')
                ->formatValue(fn ($value, Payment $entity) =>
                    number_format((float)$value, 0, ',', ' ') . ' ' . $entity->getCurrency()?->value
                ),
            TextField::new('statusLabel', 'Statut')
                ->formatValue(fn ($value, Payment $entity) => $this->renderStatusBadge($entity)),
            TextField::new('paymentMethodLabel', 'Méthode')
                ->formatValue(fn ($value, Payment $entity) => $this->renderPaymentMethodBadge($entity)),
            DateTimeField::new('createdAt')->setLabel('Date')->setFormat('dd/MM/yy HH:mm'),
        ];

        // === Champs pour DETAIL ===
        $fieldsDetail = [
            // Section: Informations générales
            FormField::addPanel('Informations générales')->setIcon('fa fa-info-circle'),
            NumberField::new('id')->setLabel('ID Paiement')->setNumDecimals(0),
            AssociationField::new('user')->setLabel('Utilisateur'),
            TextField::new('purchaseTypeLabel', 'Type d\'achat')
                ->formatValue(fn ($value, Payment $entity) => $this->renderPurchaseTypeBadge($entity, true)),
            AssociationField::new('subscriptionPlan')->setLabel('Plan d\'abonnement'),
            AssociationField::new('course')->setLabel('Cours'),
            TextField::new('statusLabel', 'Statut')
                ->formatValue(fn ($value, Payment $entity) => $this->renderStatusBadge($entity, true)),

            // Section: Montant
            FormField::addPanel('Montant')->setIcon('fa fa-money-bill'),
            NumberField::new('amount')->setLabel('Montant')
                ->formatValue(fn ($value, Payment $entity) =>
                    number_format((float)$value, 2, ',', ' ') . ' ' . $entity->getCurrency()?->value
                ),
            ChoiceField::new('currency')->setLabel('Devise')
                ->setChoices(array_combine(
                    array_map(fn($c) => $c->value, Currency::cases()),
                    Currency::cases()
                )),

            // Section: Méthode de paiement
            FormField::addPanel('Méthode de paiement')->setIcon('fa fa-credit-card'),
            TextField::new('paymentMethodLabel', 'Méthode')
                ->formatValue(fn ($value, Payment $entity) => $this->renderPaymentMethodBadge($entity, true)),
            TextField::new('phone')->setLabel('Téléphone'),
            TextField::new('reference')->setLabel('Référence'),

            // Section: Transaction (pour paiement mobile/bank)
            FormField::addPanel('Détails de la transaction')->setIcon('fa fa-exchange-alt')
                ->setHelp('Informations retournées par le processeur de paiement'),
            TextField::new('transactionId')->setLabel('ID Transaction'),
            DateTimeField::new('paidAt')->setLabel('Payé le'),
            DateTimeField::new('responsedAt')->setLabel('Réponse callback'),
            TextareaField::new('notes')->setLabel('Notes / Réponse FlexPay'),

            // Section: Métadonnées
            FormField::addPanel('Métadonnées')->setIcon('fa fa-clock'),
            DateTimeField::new('createdAt')->setLabel('Créé le'),
            DateTimeField::new('updatedAt')->setLabel('Modifié le'),
        ];

        // === Champs pour EDIT/NEW ===
        $fieldsForm = [
            AssociationField::new('user')->setLabel('Utilisateur'),
            ChoiceField::new('purchaseType')->setLabel('Type d\'achat')
                ->setChoices(array_combine(
                    array_map(fn($t) => $t->getLabel(), PurchaseType::cases()),
                    PurchaseType::cases()
                )),
            AssociationField::new('subscriptionPlan')->setLabel('Plan d\'abonnement')
                ->setHelp('Requis si type = Abonnement'),
            AssociationField::new('course')->setLabel('Cours')
                ->setHelp('Requis si type = Cours'),
            NumberField::new('amount')->setLabel('Montant'),
            ChoiceField::new('currency')->setLabel('Devise')
                ->setChoices(array_combine(
                    array_map(fn($c) => $c->value, Currency::cases()),
                    Currency::cases()
                )),
            ChoiceField::new('status')->setLabel('Statut')
                ->setChoices(array_combine(
                    array_map(fn($s) => $s->getLabel(), PaymentStatus::cases()),
                    PaymentStatus::cases()
                )),
            ChoiceField::new('paymentMethod')->setLabel('Méthode de paiement')
                ->setChoices(array_combine(
                    array_map(fn($m) => $m->getLabel(), PaymentMethod::cases()),
                    PaymentMethod::cases()
                )),
            TextField::new('phone')->setLabel('Téléphone'),
            TextField::new('transactionId')->setLabel('ID de transaction'),
            TextField::new('reference')->setLabel('Référence'),
            DateTimeField::new('paidAt')->setLabel('Payé le'),
            TextareaField::new('notes')->setLabel('Notes'),
        ];

        return match ($pageName) {
            Crud::PAGE_INDEX => $fieldsIndex,
            Crud::PAGE_DETAIL => $fieldsDetail,
            default => $fieldsForm,
        };
    }

    /**
     * Action pour valider un paiement cash
     */
    public function validateCashPayment(AdminContext $context, Request $request): Response
    {
        $payment = $this->getPaymentFromContext($context, $request);

        if (!$payment) {
            $this->addFlash('danger', 'Paiement introuvable.');
            return $this->redirectToIndex();
        }

        if ($payment->getStatus()->isFinal()) {
            $this->addFlash('warning', 'Ce paiement est déjà dans un état final.');
            return $this->redirectToDetail($payment);
        }

        // Mettre à jour le statut
        $payment->setStatus(PaymentStatus::COMPLETED);
        $payment->setPaidAt(new DateTime());
        $payment->setNotes(($payment->getNotes() ?? '') . "\nValidé manuellement le " . date('d/m/Y H:i'));

        // Activer l'achat via le PaymentManager (gère abonnement et cours)
        $this->paymentManager->activatePurchase($payment);

        $this->entityManager->flush();

        $productType = $payment->getPurchaseType() === PurchaseType::COURSE ? 'Accès au cours' : 'Abonnement';
        $this->addFlash('success', sprintf(
            'Paiement #%d validé avec succès. %s activé pour %s.',
            $payment->getId(),
            $productType,
            $payment->getUser()->getEmail()
        ));

        return $this->redirectToDetail($payment);
    }

    /**
     * Action pour rejeter un paiement
     */
    public function rejectPayment(AdminContext $context, Request $request): Response
    {
        $payment = $this->getPaymentFromContext($context, $request);

        if (!$payment) {
            $this->addFlash('danger', 'Paiement introuvable.');
            return $this->redirectToIndex();
        }

        if ($payment->getStatus()->isFinal()) {
            $this->addFlash('warning', 'Ce paiement est déjà dans un état final.');
            return $this->redirectToDetail($payment);
        }

        $payment->setStatus(PaymentStatus::REJECTED);
        $payment->setNotes(($payment->getNotes() ?? '') . "\nRejeté manuellement le " . date('d/m/Y H:i'));

        $this->entityManager->flush();

        $this->addFlash('info', sprintf('Paiement #%d rejeté.', $payment->getId()));

        return $this->redirectToDetail($payment);
    }

    /**
     * Récupère le Payment depuis le contexte ou la requête
     */
    private function getPaymentFromContext(AdminContext $context, Request $request): ?Payment
    {
        // Récupérer l'ID depuis la requête (prioritaire car toujours disponible)
        $entityId = $request->query->get('entityId');
        if ($entityId) {
            return $this->entityManager->getRepository(Payment::class)->find($entityId);
        }

        // Essayer via le contexte EasyAdmin (peut être null)
        try {
            $entity = $context->getEntity();
            if ($entity && $entity->getInstance() instanceof Payment) {
                return $entity->getInstance();
            }
        } catch (\TypeError $e) {
            // getEntity() peut retourner null et lever une TypeError
        }

        return null;
    }

    /**
     * Redirection vers la liste des paiements
     */
    private function redirectToIndex(): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }

    private function redirectToDetail(Payment $payment): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($payment->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

    /**
     * Render le statut en badge HTML
     */
    private function renderStatusBadge(Payment $payment, bool $withDescription = false): string
    {
        $status = $payment->getStatus();
        if (!$status) {
            return '<span class="badge bg-secondary">Inconnu</span>';
        }

        $badge = sprintf(
            '<span class="badge bg-%s text-white"><i class="fa %s me-1"></i>%s</span>',
            $status->getCssClass(),
            $status->getIcon(),
            $status->getLabel()
        );

        if ($withDescription) {
            $badge .= sprintf('<br><small class="text-muted">%s</small>', $status->getDescription());
        }

        return $badge;
    }

    /**
     * Render la méthode de paiement en badge HTML
     */
    private function renderPaymentMethodBadge(Payment $payment, bool $withDetails = false): string
    {
        $method = $payment->getPaymentMethod();
        if (!$method) {
            return '<span class="badge bg-secondary">Non défini</span>';
        }

        $badge = sprintf(
            '<span class="badge bg-%s text-white"><i class="fa %s me-1"></i>%s</span>',
            $method->getCssClass(),
            $method->getIcon(),
            $method->getLabel()
        );

        if ($withDetails) {
            $details = [];
            if ($method === PaymentMethod::MOBILE && $payment->getPhone()) {
                $details[] = sprintf('<i class="fa fa-phone me-1"></i>%s', $payment->getPhone());
            }
            if (!empty($details)) {
                $badge .= '<br><small class="text-muted">' . implode(' | ', $details) . '</small>';
            }
            $badge .= sprintf('<br><small class="text-muted fst-italic">%s</small>', $method->getDescription());
        }

        return $badge;
    }

    /**
     * Render le type d'achat en badge HTML
     */
    private function renderPurchaseTypeBadge(Payment $payment, bool $withDetails = false): string
    {
        $type = $payment->getPurchaseType();

        $badge = sprintf(
            '<span class="badge bg-%s text-white"><i class="fa %s me-1"></i>%s</span>',
            $type->getCssClass(),
            $type->getIcon(),
            $type->getLabel()
        );

        if ($withDetails) {
            $badge .= sprintf('<br><small class="text-muted fst-italic">%s</small>', $type->getDescription());
        }

        return $badge;
    }
}

