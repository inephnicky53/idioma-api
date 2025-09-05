<?php

namespace App\Controller\Admin;

use App\Entity\Transaction;
use App\Idioma;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;

class TransactionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Transaction::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des transactions')
            ->setPageTitle(Crud::PAGE_DETAIL, fn(Transaction $transaction) => sprintf('Transaction #%s', $transaction->getReference()))
            ->setEntityLabelInPlural('Transactions')
            ->setEntityLabelInSingular('Transaction')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(25)
            ->setSearchFields(['reference', 'providerReference', 'phone', 'message'])
            ->setAutofocusSearch();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::EDIT, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN')
            ->disable(Action::NEW); // Les transactions sont créées via l'API
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', '#')
            ->hideOnForm();

        yield TextField::new('reference', 'Référence')
            ->hideOnForm()
            ->setColumns(6);

        yield TextField::new('providerReference', 'Réf. Fournisseur')
            ->hideOnForm()
            ->hideOnIndex()
            ->setColumns(6);

        yield ChoiceField::new('operator', 'Opérateur')
            ->setChoices([
                'Mobile Money' => Transaction::OPERATOR_MOBILE,
                'Virement Bancaire' => Transaction::OPERATOR_BANK,
                'PayPal' => Transaction::OPERATOR_PAYPAL,
            ])
            ->renderAsBadges([
                Transaction::OPERATOR_MOBILE => 'success',
                Transaction::OPERATOR_BANK => 'info',
                Transaction::OPERATOR_PAYPAL => 'warning',
            ])
            ->setColumns(4);

        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'Créée' => Idioma::STATUS_CREATED,
                'En attente' => Idioma::STATUS_WAIT,
                'Validée' => Idioma::STATUS_VALIDATED,
                'Échouée' => Idioma::STATUS_FAILED,
            ])
            ->renderAsBadges([
                Idioma::STATUS_CREATED => 'secondary',
                Idioma::STATUS_WAIT => 'warning',
                Idioma::STATUS_VALIDATED => 'success',
                Idioma::STATUS_FAILED => 'danger',
            ])
            ->setColumns(4);

        yield MoneyField::new('amount', 'Montant')
            ->setCurrencyPropertyPath('currency')
            ->setStoredAsCents(false)
            ->setColumns(4);

        yield MoneyField::new('fee', 'Frais')
            ->setCurrencyPropertyPath('currency')
            ->setStoredAsCents(false)
            ->hideOnIndex()
            ->setColumns(4);

        yield AssociationField::new('currency', 'Devise')
            ->setColumns(4);

        yield TelephoneField::new('phone', 'Téléphone')
            ->hideOnIndex()
            ->setColumns(6);

        yield AssociationField::new('user', 'Utilisateur')
            ->setQueryBuilder(function (QueryBuilder $qb) {
                return $qb->orderBy('entity.email', 'ASC');
            })
            ->setColumns(6);

        yield AssociationField::new('command', 'Commande')
            ->hideOnIndex()
            ->onlyOnDetail();

        yield AssociationField::new('fees', 'Frais appliqués')
            ->hideOnIndex()
            ->onlyOnDetail();

        yield TextField::new('message', 'Message')
            ->hideOnIndex()
            ->onlyOnDetail()
            ->setMaxLength(500);

        yield DateTimeField::new('createdAt', 'Date de création')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setColumns(6);

        yield DateTimeField::new('respondedAt', 'Date de réponse')
            ->hideOnForm()
            ->hideOnIndex()
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setColumns(6);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')
                ->setChoices([
                    'Créée' => Idioma::STATUS_CREATED,
                    'En attente' => Idioma::STATUS_WAIT,
                    'Validée' => Idioma::STATUS_VALIDATED,
                    'Échouée' => Idioma::STATUS_FAILED,
                ]))
            ->add(ChoiceFilter::new('operator')
                ->setChoices([
                    'Mobile Money' => Transaction::OPERATOR_MOBILE,
                    'Virement Bancaire' => Transaction::OPERATOR_BANK,
                    'PayPal' => Transaction::OPERATOR_PAYPAL,
                ]))
            ->add(EntityFilter::new('currency'))
            ->add(EntityFilter::new('user'))
            ->add(NumericFilter::new('amount'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(DateTimeFilter::new('respondedAt'));
    }
}
