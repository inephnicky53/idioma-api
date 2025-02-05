<?php

namespace App\Controller\Admin\Payment;

use App\Entity\Payment;
use App\Exception\PaymentException;
use App\Idioma;
use App\Service\Payment\PaymentManager;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PaymentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly PaymentManager    $manager
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
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des paiements')
            ->setEntityLabelInPlural('Paiements')
            ->setEntityLabelInSingular('Paiement');
    }

    public function configureActions(Actions $actions): Actions
    {
        $validate = Action::new('validate', 'Paiement effectué')
            ->setIcon('fas fa-check')
            ->setCssClass('btn btn-success')
            ->linkToCrudAction('validate')
            ->displayIf(fn(Payment $payment) => $payment->getStatus() === Idioma::STATUS_CREATED);

        $decline = Action::new('decline', 'Refuser le paiement')
            ->setIcon('fas fa-xmark')
            ->setCssClass('btn btn-danger')
            ->linkToCrudAction('decline')
            ->displayIf(fn(Payment $payment) => $payment->getStatus() === Idioma::STATUS_CREATED);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $validate)
            ->add(Crud::PAGE_DETAIL, $decline)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('reference', 'Réf.')
            ->hideOnForm();

        yield ChoiceField::new('type', 'Type')
            ->setChoices(array_flip(Payment::getTypeListForView()));

        yield ChoiceField::new('method', 'Méthode')
            ->setChoices(array_flip(Payment::getMethodListForView()));

        yield MoneyField::new('amount', 'Montant')
            ->setCurrencyPropertyPath('currency')
            ->setStoredAsCents(false);

        yield AssociationField::new('currency', "Dévise");

        yield TextField::new('methodData', 'Compte à créditer.');

        yield AssociationField::new('user', "Utilisateur")
            ->setQueryBuilder(function (QueryBuilder $qb) {
                return $qb
                    ->join('entity.teacher', 't')
                    ->where('t IS NOT NULL');
            });

        yield ChoiceField::new('status', 'Status')
            ->setChoices(array_flip(Payment::getStatusListForView()))
            ->renderAsBadges(Payment::getStatusBadge())
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Date de création')
            ->onlyOnDetail();

        yield DateTimeField::new('updatedAt', 'Dernière modification')
            ->onlyOnDetail();

        yield DateTimeField::new('paidAt', 'Date de paiement')
            ->onlyOnDetail();
    }

    public function validate(AdminContext $context): RedirectResponse
    {
        /** @var Payment $payment */
        $payment = $context->getEntity()->getInstance();

        try {
            $payment = $this->manager->validate($payment);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $url = $this->adminUrlGenerator
            ->setController(PaymentCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($payment->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

    public function decline(AdminContext $context): RedirectResponse
    {
        /** @var Payment $payment */
        $payment = $context->getEntity()->getInstance();

        try {
            $payment = $this->manager->decline($payment);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $url = $this->adminUrlGenerator
            ->setController(PaymentCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($payment->getId())
            ->generateUrl();

        return $this->redirect($url);
    }
}
