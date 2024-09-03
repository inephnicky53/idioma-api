<?php

namespace App\Controller\Admin\User;

use App\Controller\Admin\AttachmentCrudController;
use App\Entity\User;
use App\Event\UserPwdResetedEvent;
use App\Service\SmsService;
use App\Utils\Generator;
use Doctrine\ORM\EntityManagerInterface;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\LocaleField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Liste des utilisateurs');
    }

    public function createIndexQueryBuilder(
        SearchDto        $searchDto,
        EntityDto        $entityDto,
        FieldCollection  $fields,
        FilterCollection $filters
    ): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // if user defined sort is not set
        if (0 === count($searchDto->getSort())) {
            $queryBuilder
                ->addSelect('CONCAT(entity.firstname, \' \', entity.name) AS HIDDEN fullname')
                ->addOrderBy('fullname', 'DESC');
        }

        return $queryBuilder;
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Info du compte')
            ->setHelp("Les informations liés au compte de l'utilisateur");
        yield IdField::new('id')->hideOnForm();

        yield CollectionField::new('thumbnails', 'Image')
            ->setTemplatePath('admin/field/media.html.twig')
            ->hideOnForm()
            ->allowDelete(false)
            ->setColumns(6);

        yield TextField::new('fullname', "Nom complet")
            ->hideOnForm()
            ->setColumns(6);

        yield EmailField::new('email')->hideOnIndex()
            ->setHelp("Cet adresse e-mail doit être unique")
            ->setColumns(6);
        yield CountryField::new('country', "Pays")->onlyOnForms()
            ->setHelp("Le pays où se trouve l'utilisateur")
            ->setColumns(6);

        yield TelephoneField::new('phone', "Téléphone")
            ->setHelp("Le téléphone de l'utilisateur sans indicatif (0823232888)")
            ->setColumns(6);

        yield TextField::new('password', "Mot de passe")
            ->hideOnIndex()
            ->hideWhenUpdating()
            ->hideOnDetail()
            ->setColumns(6);

        yield BooleanField::new('is_sure', "Compte sûr ?")
            ->hideOnForm()
            ->hideOnIndex()
            ->setHelp("Si le compte de l'utilisateur est sûr")
            ->setColumns(6);

        yield BooleanField::new('isVerified', "Si e-mail vérifié")
            ->hideOnIndex()
            ->setHelp("Si l'adresse e-mail de l'utilisateur est vérifié")
            ->setColumns(6);

        yield ChoiceField::new('roles')->setChoices(User::getRolesList())
            ->allowMultipleChoices()
            ->setHelp("Les roles de l'utilisateur")
            ->setColumns(6);

        yield LocaleField::new('language', "Langue sélectionnée")
            ->hideOnIndex()
            ->setColumns(6);

        yield BooleanField::new('is_active', "Est actif ?")
            ->setHelp("Si le compte de l'utilisateur est actif")
            ->setColumns(6);

        yield CollectionField::new('thumbnails', 'Image')
            ->useEntryCrudForm(AttachmentCrudController::class)
            ->onlyOnForms()
            ->allowDelete(false)
            ->setColumns(6);

        yield BooleanField::new('isPhoneVerified', "Si téléphone vérifié")
            ->hideOnIndex()
            ->setColumns(6);


        yield FormField::addTab('Info utilisateur')
            ->setHelp("Les informations liées à l'utilisateur")
            ->hideOnForm();

        yield TextField::new('name', "Nom")
            ->hideOnIndex()
            ->setColumns(6);

        yield TextField::new('postname', "Postnom")
            ->hideOnIndex()
            ->setColumns(6);

        yield TextField::new('firstname', "Prénom")
            ->hideOnIndex()
            ->setColumns(6);

        yield DateField::new('birthdayAt', "Date de naissance")
            ->hideOnIndex()
            ->setColumns(6);

        yield TextField::new('lastLoginIp', 'Dernier Ip')
            ->onlyOnDetail();

        yield DateTimeField::new('lastLoginAt', 'Dernière connexion')
            ->onlyOnDetail();

        yield DateTimeField::new('createdAt', 'Date de création')
            ->onlyOnDetail();

        yield DateTimeField::new('updatedAt', 'Date de modification')
            ->onlyOnDetail();


        yield FormField::addTab('Localisation')
            ->hideOnForm()
            ->setHelp("Les informations sur la localisation");

        yield CountryField::new('country', "Pays")
            ->hideOnForm()
            ->setColumns(6);

        yield TextField::new('city', "Ville")
            ->onlyOnDetail()
            ->setColumns(6);

        yield TextField::new('subdivisions', "Province")
            ->onlyOnDetail()
            ->setColumns(6);

        yield TextField::new('ip', "IP")->onlyOnDetail()
            ->setColumns(6);

        yield TextField::new('timezone', "Zone")->onlyOnDetail()
            ->setColumns(6);

        yield TextField::new('postal', "Code postal")->onlyOnDetail()
            ->setColumns(6);

        yield TextField::new('isp', "Réseau")->onlyOnDetail()
            ->setColumns(6);

        yield TextField::new('longitude', "Longitude")->onlyOnDetail()
            ->setColumns(6);

        yield TextField::new('latitude', "Latitude")->onlyOnDetail()
            ->setColumns(6);

        yield FormField::addTab('Langues a apprendre (objectifs)')
            ->setHelp("Les langues que l'utilisateur désire apprendre");

        yield AssociationField::new('goals', "Langues a apprendre (objectifs)")
            ->hideOnIndex()
            ->setTemplatePath('admin/field/goals.html.twig')
            ->autocomplete();


        yield FormField::addTab('Heures et professeurs suivis')
            ->onlyOnDetail()
            ->setHelp("Les professeurs que l'utilisateur suit");

        yield NumberField::new('hours', "Nombre d'heures totales")
            ->onlyOnDetail();

        yield AssociationField::new('teachers', "Professeurs")
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/teachers.html.twig');


        yield FormField::addTab('Plannings')
            ->onlyOnDetail()
            ->setHelp("Le planning de l'élève");

        yield AssociationField::new('plannings', "Plannings")
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/user_plannings.html.twig');
    }


    public function configureActions(Actions $actions): Actions
    {
        $pwdChange = Action::new('pwdChange', 'Réinitialiser le mot de passe', 'fas fa-key')
            ->linkToCrudAction('resetPwdAction');
        $approveUser = Action::new('approve', "Approuver l'utilisateur", 'fa fa-user-check')
            ->linkToCrudAction('approveUserAction');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $approveUser)
            ->add(Crud::PAGE_INDEX, $pwdChange)
            ->add(Crud::PAGE_DETAIL, $approveUser)
            ->add(Crud::PAGE_DETAIL, $pwdChange)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
    }

    public function approveUserAction(
        AdminContext           $context,
        EntityManagerInterface $entityManager,
        AdminUrlGenerator      $adminUrlGenerator
    ): RedirectResponse
    {
        $id = $context->getRequest()->query->get('entityId');
        /** @var User $user */
        $user = $entityManager->getRepository(User::class)->find($id);
        $user->setIsVerified(true);

        $entityManager->persist($user);
        $entityManager->flush();

        $message = "Le compte de {$user->getFullname()} est approuvé avec succès";

        $this->addFlash('success', $message);


        $url = $adminUrlGenerator
            ->setAction(Action::DETAIL)
            ->removeReferrer()
            ->setController($context->getCrud()?->getControllerFqcn() ?? '')
            ->generateUrl();

        return $this->redirect($url);
    }

    public function resetPwdAction(
        AdminContext                $context,
        AdminUrlGenerator           $adminUrlGenerator,
        EntityManagerInterface      $entityManager,
    ): RedirectResponse
    {
        $id = $context->getRequest()->query->get('entityId');
        /** @var User $user */
        $user = $entityManager->getRepository(User::class)->find($id);
        $this->dispatcher->dispatch(new UserPwdResetedEvent($user));

        $url = $adminUrlGenerator
            ->setAction(Action::DETAIL)
            ->removeReferrer()
            ->setController($context->getCrud()?->getControllerFqcn() ?? '')
            ->generateUrl();

        return $this->redirect($url);

    }

}
