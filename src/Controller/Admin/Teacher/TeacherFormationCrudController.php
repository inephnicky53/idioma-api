<?php

namespace App\Controller\Admin\Teacher;

use App\Entity\TeacherFormation;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class TeacherFormationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TeacherFormation::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield AssociationField::new('teacher', "Idiomaster")
            ->autocomplete()
            ->setColumns(6);

        yield TextField::new('university', "Université/École")
            ->setColumns(6)
            ->setHelp('Nom de l\'établissement d\'enseignement');

        yield TextField::new('certificate', "Diplôme/Certificat")
            ->setColumns(6)
            ->setHelp('Nom du diplôme ou certificat obtenu');

        yield TextField::new('speciality', "Spécialité")
            ->setColumns(6)
            ->setHelp('Domaine de spécialisation (optionnel)');

        yield IntegerField::new('yearStart', "Année de début")
            ->setColumns(3)
            ->setHelp('Année de début de formation');

        yield IntegerField::new('yearEnd', "Année de fin")
            ->setColumns(3)
            ->setHelp('Année d\'obtention du diplôme');

        yield UrlField::new('proofImage', "Image de preuve")
            ->setColumns(12)
            ->setHelp('URL de l\'image du diplôme ou de la preuve');

        yield AssociationField::new('file', "Fichier joint")
            ->setColumns(6)
            ->setHelp('Fichier PDF ou image du diplôme');
    }
}