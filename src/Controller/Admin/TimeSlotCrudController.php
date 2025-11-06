<?php

namespace App\Controller\Admin;

use App\Entity\TimeSlot;
use App\Trait\FrenchActionsTrait;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TimeSlotCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

    public static function getEntityFqcn(): string
    {
        return TimeSlot::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Créneau horaire')
            ->setEntityLabelInPlural('Créneaux horaires')
            ->setSearchFields(['day', 'time'])
            ->setDefaultSort(['day' => 'ASC', 'time' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            ChoiceField::new('day')->setLabel('Jour')
                ->setChoices([
                    'Lundi' => 'Monday',
                    'Mardi' => 'Tuesday',
                    'Mercredi' => 'Wednesday',
                    'Jeudi' => 'Thursday',
                    'Vendredi' => 'Friday',
                    'Samedi' => 'Saturday',
                    'Dimanche' => 'Sunday',
                ]),
            TextField::new('startTime')->setLabel('Heure de début')
                ->setHelp('Format: HH:MM (ex: 18:00)'),
            TextField::new('endTime')->setLabel('Heure de fin')
                ->setHelp('Format: HH:MM (ex: 20:00)'),
            TextField::new('time')->setLabel('Heure (format complet)')
                ->setHelp('Format: HH:MM–HH:MM (ex: 18:00–20:00)')
                ->hideOnForm(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = $this->configureFrenchActions($actions);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setIcon('fa fa-plus');
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setIcon('fa fa-edit');
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash');
            });
    }
}
