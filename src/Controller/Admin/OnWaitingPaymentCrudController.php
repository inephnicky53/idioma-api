<?php

namespace App\Controller\Admin;

use App\Entity\Payment;
use App\Idioma;
use App\Service\Payment\PaymentManager;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

class OnWaitingPaymentCrudController extends AbstractCrudController
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
            ->setPageTitle(Crud::PAGE_INDEX, "Paiements en attente d'activation")
            ->setEntityLabelInPlural('Paiements')
            ->setEntityLabelInSingular('Paiement');
    }

    public function configureActions(Actions $actions): Actions
    {
        $validate = Action::new('validate', 'Valider le paiement')
            ->setIcon('fas fa-check')
            ->linkToCrudAction('validatePayment');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $validate)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Informations générale')
            ->setHelp("Les informations générales du professeur");

        yield IdField::new('id')
            ->hideOnForm();
    }

    public function validatePayment(AdminContext $context): RedirectResponse
    {
        /** @var Payment $order */
        $payment = $context->getEntity()->getInstance();

        $this->manager->validate($payment);

        $url = $this->adminUrlGenerator
            ->setController(OnWaitingPaymentCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.status = :status')
            ->setParameter('status', Idioma::STATUS_WAIT);
    }
}
