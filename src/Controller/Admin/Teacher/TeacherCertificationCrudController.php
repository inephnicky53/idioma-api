<?php

namespace App\Controller\Admin\Teacher;

use App\Entity\TeacherCertification;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class TeacherCertificationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TeacherCertification::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield AssociationField::new('teacher', "Idiomaster")
            ->autocomplete()
            ->setColumns(6);

        yield TextField::new('certification', "Nom de la certification")
            ->setColumns(6)
            ->setHelp('Nom du certificat ou de la certification');

        yield AssociationField::new('language', "Langues")
            ->autocomplete()
            ->setColumns(12)
            ->setHelp('Langues concernées par cette certification');

        yield DateTimeField::new('yearStart', "Date de début")
            ->setColumns(6)
            ->setFormat('dd/MM/yyyy')
            ->setHelp('Date d\'obtention ou de début de validité');

        yield DateTimeField::new('yearEnd', "Date de fin")
            ->setColumns(6)
            ->setFormat('dd/MM/yyyy')
            ->setHelp('Date de fin de validité (optionnel)');

        yield UrlField::new('proofImage', "Image de preuve")
            ->setColumns(12)
            ->setHelp('URL de l\'image du certificat ou de la preuve');

        yield AssociationField::new('file', "Fichier joint")
            ->setColumns(6)
            ->setHelp('Fichier PDF ou image du certificat');

        yield BooleanField::new('isVerified', "Vérifié")
            ->renderAsSwitch(false)
            ->setColumns(6);

        yield DateTimeField::new('createdAt', "Créé le")
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm');
    }
}