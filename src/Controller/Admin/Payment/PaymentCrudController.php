<?php

namespace App\Controller\Admin\Payment;

use App\Entity\Payment;
use App\Entity\User;
use App\Idioma;
use App\Service\Payment\PaymentManager;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PaymentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly PaymentManager    $manager,
        private readonly LoggerInterface   $logger,
        private readonly Security          $security
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Gestion des paiements')
            ->setPageTitle(Crud::PAGE_NEW, 'Créer un nouveau paiement')
            ->setPageTitle(Crud::PAGE_EDIT, fn(Payment $payment) => sprintf('Modifier le paiement #%s', $payment->getReference()))
            ->setPageTitle(Crud::PAGE_DETAIL, fn(Payment $payment) => sprintf('Paiement #%s', $payment->getReference()))
            ->setEntityLabelInPlural('Paiements')
            ->setEntityLabelInSingular('Paiement')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(25)
            ->setSearchFields(['reference', 'methodData', 'user.email', 'user.name', 'user.firstname'])
            ->setAutofocusSearch()
            ->showEntityActionsInlined();
    }

    public function createIndexQueryBuilder(
        SearchDto        $searchDto,
        EntityDto        $entityDto,
        FieldCollection  $fields,
        FilterCollection $filters
    ): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $queryBuilder
            ->leftJoin('entity.user', 'u')
            ->addSelect('u')
            ->leftJoin('entity.currency', 'c')
            ->addSelect('c')
            ->leftJoin('entity.executedBy', 'e')
            ->addSelect('e');

        return $queryBuilder;
    }

    public function configureActions(Actions $actions): Actions
    {
        $validate = Action::new('validate', 'Valider le paiement')
            ->setIcon('fas fa-check')
            ->setCssClass('btn btn-success')
            ->linkToCrudAction('validate')
            ->displayIf(fn(Payment $payment) => $payment->getStatus() == Idioma::STATUS_CREATED);

        $decline = Action::new('decline', 'Refuser le paiement')
            ->setIcon('fas fa-times')
            ->setCssClass('btn btn-danger')
            ->linkToCrudAction('decline')
            ->displayIf(fn(Payment $payment) => $payment->getStatus() == Idioma::STATUS_CREATED);

        $resend = Action::new('resend', 'Renvoyer notification')
            ->setIcon('fas fa-paper-plane')
            ->setCssClass('btn btn-info')
            ->linkToCrudAction('resendNotification')
            ->displayIf(fn(Payment $payment) => in_array($payment->getStatus(), [Idioma::STATUS_VALIDATED, Idioma::STATUS_DECLINED]));

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $validate)
            ->add(Crud::PAGE_DETAIL, $decline)
            ->add(Crud::PAGE_DETAIL, $resend)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::EDIT, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN')
            ->setPermission('validate', 'ROLE_ADMIN')
            ->setPermission('decline', 'ROLE_ADMIN')
            ->setPermission('resend', 'ROLE_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id', '#')
                ->setColumns(1);

            yield TextField::new('reference', 'Référence')
                ->setColumns(2);

            yield TextField::new('user.fullname', 'Utilisateur')
                ->setColumns(2);

            yield MoneyField::new('amount', 'Montant')
                ->setCurrencyPropertyPath('currency')
                ->setStoredAsCents(false)
                ->setColumns(2);

            yield ChoiceField::new('status', 'Statut')
                ->setChoices(array_flip(Payment::getStatusListForView()))
                ->renderAsBadges(Payment::getStatusBadge())
                ->setColumns(2);

            yield DateTimeField::new('createdAt', 'Date')
                ->setFormat('dd/MM/yyyy')
                ->setColumns(2);

            return;
        }

        yield FormField::addTab('Informations principales')
            ->setIcon('fas fa-credit-card')
            ->setHelp('Détails du paiement et informations de base');

        yield IdField::new('id', '#')
            ->hideOnForm()
            ->setColumns(2);

        yield TextField::new('reference', 'Référence')
            ->hideOnForm()
            ->setHelp('Référence unique du paiement')
            ->setColumns(4);

        yield ChoiceField::new('status', 'Statut')
            ->setChoices(array_flip(Payment::getStatusListForView()))
            ->renderAsBadges(Payment::getStatusBadge())
            ->hideOnForm()
            ->setColumns(3);

        yield MoneyField::new('amount', 'Montant')
            ->setCurrencyPropertyPath('currency')
            ->setStoredAsCents(false)
            ->setHelp('Montant du paiement')
            ->setColumns(3);

        yield AssociationField::new('currency', 'Devise')
            ->setHelp('Devise du paiement')
            ->setColumns(6);

        yield ChoiceField::new('type', 'Type de paiement')
            ->setChoices(array_flip(Payment::getTypeListForView()))
            ->renderAsBadges([
                'withdrawal' => 'primary',
                'deposit' => 'success',
                'transfer' => 'info'
            ])
            ->setHelp('Type de transaction')
            ->setColumns(6);

        yield FormField::addTab('Méthode de paiement')
            ->setIcon('fas fa-wallet')
            ->setHelp('Informations sur la méthode de paiement utilisée');

        yield ChoiceField::new('method', 'Méthode')
            ->setChoices(array_flip(Payment::getMethodListForView()))
            ->renderAsBadges([
                'mobile_money' => 'warning',
                'bank_transfer' => 'info',
                'cash' => 'secondary',
                'card' => 'primary'
            ])
            ->setHelp('Méthode de paiement utilisée')
            ->setColumns(6);

        yield TextField::new('methodData', 'Informations de paiement')
            ->setHelp('Numéro de compte, téléphone ou autres informations de paiement')
            ->setColumns(6);

        yield FormField::addTab('Utilisateur')
            ->setIcon('fas fa-user')
            ->setHelp('Informations sur l\'utilisateur concerné par le paiement');

        yield AssociationField::new('user', 'Utilisateur')
            ->setQueryBuilder(function (QueryBuilder $qb) {
                return $qb
                    ->leftJoin('entity.teacher', 't')
                    ->where('t IS NOT NULL OR entity.roles LIKE :role')
                    ->setParameter('role', '%ROLE_TEACHER%')
                    ->orderBy('entity.email', 'ASC');
            })
            ->setHelp('Utilisateur bénéficiaire du paiement')
            ->autocomplete()
            ->setColumns(12);

        yield FormField::addTab('Suivi')
            ->setIcon('fas fa-history')
            ->setHelp('Historique et suivi du paiement');

        yield AssociationField::new('executedBy', 'Traité par')
            ->hideOnForm()
            ->setHelp('Administrateur ayant traité le paiement')
            ->setColumns(6);

        yield DateTimeField::new('createdAt', 'Date de création')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setHelp('Date de création de la demande')
            ->setColumns(4);

        yield DateTimeField::new('updatedAt', 'Dernière modification')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setHelp('Date de dernière modification')
            ->setColumns(4);

        yield DateTimeField::new('paidAt', 'Date de paiement')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setHelp('Date effective du paiement')
            ->setColumns(4);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')
                ->setChoices(array_flip(Payment::getStatusListForView())))
            ->add(ChoiceFilter::new('type', 'Type')
                ->setChoices(array_flip(Payment::getTypeListForView())))
            ->add(ChoiceFilter::new('method', 'Méthode')
                ->setChoices(array_flip(Payment::getMethodListForView())))
            ->add(EntityFilter::new('currency', 'Devise'))
            ->add(EntityFilter::new('user', 'Utilisateur'))
            ->add(TextFilter::new('reference', 'Référence'))
            ->add(TextFilter::new('methodData', 'Infos paiement'))
            ->add(NumericFilter::new('amount', 'Montant'))
            ->add(DateTimeFilter::new('createdAt', 'Date création'))
            ->add(DateTimeFilter::new('paidAt', 'Date paiement'));
    }

    public function validate(AdminContext $context): RedirectResponse
    {
        /** @var Payment $payment */
        $payment = $context->getEntity()->getInstance();

        try {
            /** @var User $adminUser */
            $adminUser = $this->security->getUser();

            $payment = $this->manager->validate($payment);

            $this->logger->info('Payment validated', [
                'payment_id' => $payment->getId(),
                'payment_reference' => $payment->getReference(),
                'admin_id' => $adminUser->getId(),
                'amount' => $payment->getAmount()
            ]);

            $this->addFlash('success', sprintf(
                'Paiement #%s validé avec succès. Montant : %s %s',
                $payment->getReference(),
                number_format($payment->getAmount(), 2),
                $payment->getCurrency()->getMin()
            ));
        } catch (\Exception $e) {
            $this->logger->error('Failed to validate payment', [
                'payment_id' => $payment->getId(),
                'error' => $e->getMessage()
            ]);
            $this->addFlash('error', 'Erreur lors de la validation : ' . $e->getMessage());
        }

        return $this->redirectToDetail($payment);
    }

    public function decline(AdminContext $context): RedirectResponse
    {
        /** @var Payment $payment */
        $payment = $context->getEntity()->getInstance();

        try {
            /** @var User $adminUser */
            $adminUser = $this->security->getUser();

            $payment = $this->manager->decline($payment);

            $this->logger->info('Payment declined', [
                'payment_id' => $payment->getId(),
                'payment_reference' => $payment->getReference(),
                'admin_id' => $adminUser->getId()
            ]);

            $this->addFlash('warning', sprintf(
                'Paiement #%s refusé.',
                $payment->getReference()
            ));
        } catch (\Exception $e) {
            $this->logger->error('Failed to decline payment', [
                'payment_id' => $payment->getId(),
                'error' => $e->getMessage()
            ]);
            $this->addFlash('error', 'Erreur lors du refus : ' . $e->getMessage());
        }

        return $this->redirectToDetail($payment);
    }

    public function resendNotification(AdminContext $context): RedirectResponse
    {
        /** @var Payment $payment */
        $payment = $context->getEntity()->getInstance();

        try {
            // TODO: Implémenter l'envoi de notification
            // $this->notificationService->sendPaymentNotification($payment);

            $this->logger->info('Payment notification resent', [
                'payment_id' => $payment->getId(),
                'payment_reference' => $payment->getReference()
            ]);

            $this->addFlash('success', sprintf(
                'Notification renvoyée pour le paiement #%s.',
                $payment->getReference()
            ));
        } catch (\Exception $e) {
            $this->logger->error('Failed to resend notification', [
                'payment_id' => $payment->getId(),
                'error' => $e->getMessage()
            ]);
            $this->addFlash('error', 'Erreur lors de l\'envoi : ' . $e->getMessage());
        }

        return $this->redirectToDetail($payment);
    }

    private function redirectToDetail(Payment $payment): RedirectResponse
    {
        $url = $this->adminUrlGenerator
            ->setController(PaymentCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($payment->getId())
            ->generateUrl();

        return $this->redirect($url);
    }
}
