<?php

namespace App\Controller\Admin;

use App\Entity\Payment;
use App\Trait\FrenchActionsTrait;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;

class PaymentCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $this->configureFrenchActions($actions);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('user')->setLabel('Utilisateur'),
            AssociationField::new('subscriptionPlan')->setLabel('Plan d\'abonnement'),
            MoneyField::new('amount')->setLabel('Montant')->setCurrency('EUR'),
            ChoiceField::new('status')->setLabel('Statut')->setChoices([
                'En attente' => 'pending',
                'Complété' => 'completed',
                'Échoué' => 'failed',
                'Remboursé' => 'refunded',
            ]),
            TextField::new('paymentMethod')->setLabel('Méthode de paiement'),
            TextField::new('transactionId')->setLabel('ID de transaction'),
            DateTimeField::new('paidAt')->setLabel('Payé le'),
            TextEditorField::new('notes')->setLabel('Notes'),
            DateTimeField::new('createdAt')->setLabel('Créé le')->hideOnForm(),
            DateTimeField::new('updatedAt')->setLabel('Modifié le')->hideOnForm(),
        ];
    }
}

