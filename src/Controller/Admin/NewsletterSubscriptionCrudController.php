<?php

namespace App\Controller\Admin;

use App\Entity\NewsletterSubscription;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class NewsletterSubscriptionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NewsletterSubscription::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Abonnement Newsletter')
            ->setEntityLabelInPlural('Abonnements Newsletter')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(30);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->disable(Action::EDIT);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isActive', 'Actif'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield EmailField::new('email', 'Email');

        yield BooleanField::new('isActive', 'Actif')
            ->renderAsSwitch(false);

        yield DateTimeField::new('subscribedAt', 'Abonné le')
            ->setFormat('dd/MM/yyyy HH:mm');

        yield DateTimeField::new('unsubscribedAt', 'Désabonné le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnIndex();
    }
}
