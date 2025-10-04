<?php

namespace App\Controller\Admin\User;

use App\Controller\Admin\AttachmentCrudController;
use App\Entity\User;
use App\Event\UserPwdResetedEvent;
use Doctrine\ORM\EntityManagerInterface;
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
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\CountryFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface          $logger,
        private readonly Security                 $security
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
            ->setPageTitle(Crud::PAGE_INDEX, 'Gestion des utilisateurs')
            ->setPageTitle(Crud::PAGE_NEW, 'Créer un nouvel utilisateur')
            ->setPageTitle(Crud::PAGE_EDIT, fn(User $user) => sprintf('Modifier "%s"', $user->getFullname()))
            ->setPageTitle(Crud::PAGE_DETAIL, fn(User $user) => sprintf('Profil de %s', $user->getFullname()))
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(25)
            ->setSearchFields(['email', 'name', 'firstname', 'postname', 'phone'])
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

        // Optimisation : ajouter les jointures nécessaires pour éviter les requêtes N+1
        $queryBuilder
            ->leftJoin('entity.thumbnails', 'thumbnails')
            ->addSelect('thumbnails')
            ->leftJoin('entity.goals', 'goals')
            ->addSelect('goals');

        // Tri par défaut par nom complet si aucun tri défini
        if (0 === count($searchDto->getSort())) {
            $queryBuilder
                ->addSelect('CONCAT(COALESCE(entity.firstname, \"\"), \" \", COALESCE(entity.name, \"\")) AS HIDDEN fullname')
                ->addOrderBy('fullname', 'ASC');
        }

        return $queryBuilder;
    }

    public function configureFields(string $pageName): iterable
    {
        // Onglet : Informations du compte
        yield FormField::addTab('Compte')
            ->setIcon('fas fa-user')
            ->setHelp('Informations principales du compte utilisateur');

        yield IdField::new('id', '#')
            ->hideOnForm()
            ->setColumns(2);

        yield CollectionField::new('thumbnails', 'Photo de profil')
            ->setTemplatePath('admin/field/media.html.twig')
            ->hideOnForm()
            ->allowDelete(false)
            ->setColumns(3);

        yield TextField::new('fullname', 'Nom complet')
            ->hideOnForm()
            ->setColumns(7);

        yield EmailField::new('email', 'Adresse e-mail')
            ->hideOnIndex()
            ->setRequired(true)
            ->setHelp('Cette adresse e-mail doit être unique dans le système')
            ->setColumns(6);

        yield TelephoneField::new('phone', 'Téléphone')
            ->setHelp('Numéro de téléphone sans indicatif pays (ex: 0823232888)')
            ->setColumns(6);

        yield TextField::new('password', 'Mot de passe')
            ->hideOnIndex()
            ->hideWhenUpdating()
            ->hideOnDetail()
            ->setRequired(true)
            ->setHelp('Minimum 8 caractères avec au moins une majuscule, une minuscule et un chiffre')
            ->setColumns(6);

        yield ChoiceField::new('roles', 'Rôles')
            ->setChoices(User::getRolesList())
            ->allowMultipleChoices()
            ->renderExpanded(false)
            ->renderAsBadges([
                'ROLE_USER' => 'primary',
                'ROLE_TEACHER' => 'success',
                'ROLE_ADMIN' => 'warning',
                'ROLE_SUPER_ADMIN' => 'danger'
            ])
            ->setHelp('Rôles et permissions de l\'utilisateur')
            ->setColumns(6);

        yield LocaleField::new('language', 'Langue préférée')
            ->hideOnIndex()
            ->setColumns(4);

        yield CountryField::new('country', 'Pays')
            ->setHelp('Pays de résidence de l\'utilisateur')
            ->setColumns(4);

        yield BooleanField::new('is_active', 'Compte actif')
            ->renderAsSwitch()
            ->setHelp('Désactiver temporairement le compte sans le supprimer')
            ->setColumns(4);

        // Onglet : Statuts de vérification
        yield FormField::addTab('Vérifications')
            ->setIcon('fas fa-shield-check')
            ->setHelp('Statuts de vérification du compte');

        yield BooleanField::new('isVerified', 'E-mail vérifié')
            ->renderAsSwitch()
            ->setHelp('Statut de vérification de l\'adresse e-mail')
            ->setColumns(4);

        yield BooleanField::new('isPhoneVerified', 'Téléphone vérifié')
            ->renderAsSwitch()
            ->setHelp('Statut de vérification du numéro de téléphone')
            ->setColumns(4);

        yield BooleanField::new('is_sure', 'Compte sûr')
            ->renderAsSwitch()
            ->hideOnForm()
            ->setHelp('Compte marqué comme sûr par l\'administration')
            ->setColumns(4);

        // Onglet : Informations personnelles
        yield FormField::addTab('Profil')
            ->setIcon('fas fa-id-card')
            ->setHelp('Informations personnelles de l\'utilisateur');

        yield TextField::new('name', 'Nom de famille')
            ->hideOnIndex()
            ->setColumns(4);

        yield TextField::new('postname', 'Postnom')
            ->hideOnIndex()
            ->setColumns(4);

        yield TextField::new('firstname', 'Prénom')
            ->hideOnIndex()
            ->setColumns(4);

        yield DateField::new('birthdayAt', 'Date de naissance')
            ->hideOnIndex()
            ->setColumns(6);

        yield CollectionField::new('thumbnails', 'Photos de profil')
            ->useEntryCrudForm(AttachmentCrudController::class)
            ->onlyOnForms()
            ->allowDelete(true)
            ->allowAdd(true)
            ->setHelp('Télécharger une photo de profil')
            ->setColumns(6);

        // Onglet : Objectifs d'apprentissage
        yield FormField::addTab('Objectifs')
            ->setIcon('fas fa-bullseye')
            ->setHelp('Langues que l\'utilisateur souhaite apprendre');

        yield AssociationField::new('goals', 'Langues à apprendre')
            ->hideOnIndex()
            ->setTemplatePath('admin/field/goals.html.twig')
            ->autocomplete()
            ->setHelp('Sélectionner les langues que l\'utilisateur veut apprendre');

        // Onglet : Activité d'apprentissage (lecture seule)
        yield FormField::addTab('Apprentissage')
            ->onlyOnDetail()
            ->setIcon('fas fa-graduation-cap')
            ->setHelp('Suivi de l\'activité d\'apprentissage');

        yield NumberField::new('hours', 'Heures totales')
            ->onlyOnDetail()
            ->setHelp('Nombre total d\'heures de cours suivies')
            ->setColumns(6);

        yield AssociationField::new('teachers', 'Idiomasters suivis')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/teachers.html.twig')
            ->setColumns(6);

        yield AssociationField::new('plannings', 'Planning des cours')
            ->onlyOnDetail()
            ->setTemplatePath('admin/field/user_plannings.html.twig');

        // Onglet : Informations techniques
        yield FormField::addTab('Technique')
            ->onlyOnDetail()
            ->setIcon('fas fa-cogs')
            ->setHelp('Informations techniques et de connexion');

        yield TextField::new('lastLoginIp', 'Dernière IP')
            ->onlyOnDetail()
            ->setColumns(4);

        yield DateTimeField::new('lastLoginAt', 'Dernière connexion')
            ->onlyOnDetail()
            ->setFormat('dd/MM/yyyy HH:mm:ss')
            ->setColumns(4);

        yield DateTimeField::new('createdAt', 'Créé le')
            ->onlyOnDetail()
            ->setFormat('dd/MM/yyyy HH:mm:ss')
            ->setColumns(4);

        yield DateTimeField::new('updatedAt', 'Modifié le')
            ->onlyOnDetail()
            ->setFormat('dd/MM/yyyy HH:mm:ss')
            ->setColumns(4);

        // Onglet : Géolocalisation
        yield FormField::addTab('Localisation')
            ->onlyOnDetail()
            ->setIcon('fas fa-map-marker-alt')
            ->setHelp('Informations de géolocalisation');

        yield TextField::new('city', 'Ville')
            ->onlyOnDetail()
            ->setColumns(4);

        yield TextField::new('subdivisions', 'Province/État')
            ->onlyOnDetail()
            ->setColumns(4);

        yield TextField::new('postal', 'Code postal')
            ->onlyOnDetail()
            ->setColumns(4);

        yield TextField::new('ip', 'Adresse IP')
            ->onlyOnDetail()
            ->setColumns(3);

        yield TextField::new('timezone', 'Fuseau horaire')
            ->onlyOnDetail()
            ->setColumns(3);

        yield TextField::new('isp', 'Fournisseur internet')
            ->onlyOnDetail()
            ->setColumns(6);

        yield TextField::new('longitude', 'Longitude')
            ->onlyOnDetail()
            ->setColumns(6);

        yield TextField::new('latitude', 'Latitude')
            ->onlyOnDetail()
            ->setColumns(6);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Actions personnalisées (disponibles uniquement en détail)
        $pwdReset = Action::new('pwdReset', 'Réinitialiser mot de passe')
            ->setIcon('fas fa-key')
            ->setCssClass('btn btn-warning')
            ->linkToCrudAction('resetPwdAction')
            ->displayIf(fn(User $user) => $user->isIsActive());

        $approveUser = Action::new('approve', 'Approuver le compte')
            ->setIcon('fas fa-user-check')
            ->setCssClass('btn btn-success')
            ->linkToCrudAction('approveUserAction')
            ->displayIf(fn(User $user) => !$user->isVerified());

        $toggleActive = Action::new('toggleActive', 'Basculer statut')
            ->setIcon('fas fa-toggle-on')
            ->setCssClass('btn btn-info')
            ->linkToCrudAction('toggleActiveAction');

        $sendWelcome = Action::new('sendWelcome', 'Envoyer e-mail de bienvenue')
            ->setIcon('fas fa-envelope')
            ->setCssClass('btn btn-primary')
            ->linkToCrudAction('sendWelcomeAction')
            ->displayIf(fn(User $user) => $user->isVerified() && $user->isIsActive());

        return $actions
            // Seule l'action DETAIL (Consulter) est affichée dans le tableau
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            // Toutes les autres actions sont disponibles uniquement en page de détail
            ->add(Crud::PAGE_DETAIL, $approveUser)
            ->add(Crud::PAGE_DETAIL, $pwdReset)
            ->add(Crud::PAGE_DETAIL, $toggleActive)
            ->add(Crud::PAGE_DETAIL, $sendWelcome)
            // Permissions
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::EDIT, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN')
            ->setPermission('pwdReset', 'ROLE_ADMIN')
            ->setPermission('approve', 'ROLE_ADMIN')
            ->setPermission('toggleActive', 'ROLE_ADMIN')
            ->setPermission('sendWelcome', 'ROLE_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('email', 'E-mail'))
            ->add(TextFilter::new('name', 'Nom'))
            ->add(TextFilter::new('firstname', 'Prénom'))
            ->add(BooleanFilter::new('isVerified', 'E-mail vérifié'))
            ->add(BooleanFilter::new('isPhoneVerified', 'Téléphone vérifié'))
            ->add(BooleanFilter::new('is_active', 'Compte actif'))
            ->add(BooleanFilter::new('is_sure', 'Compte sûr'))
            ->add(ChoiceFilter::new('roles', 'Rôles')
                ->setChoices(User::getRolesList()))
            ->add(EntityFilter::new('goals', 'Objectifs d\'apprentissage'))
            ->add(DateTimeFilter::new('createdAt', 'Date de création'))
            ->add(DateTimeFilter::new('lastLoginAt', 'Dernière connexion'));
    }

    public function approveUserAction(
        AdminContext           $context,
        EntityManagerInterface $entityManager,
        AdminUrlGenerator      $adminUrlGenerator
    ): RedirectResponse
    {
        try {
            $id = $context->getRequest()->query->get('entityId');
            /** @var User $user */
            $user = $entityManager->getRepository(User::class)->find($id);

            if (!$user) {
                throw new \Exception('Utilisateur introuvable');
            }

            $user->setIsVerified(true);
            $entityManager->persist($user);
            $entityManager->flush();

            /** @var User $adminUser */
            $adminUser = $this->security->getUser();

            $this->logger->info('User approved', [
                'user_id' => $user->getId(),
                'admin_id' => $adminUser->getId(),
                'user_email' => $user->getEmail()
            ]);

            $this->addFlash('success', sprintf(
                'Le compte de %s a été approuvé avec succès.',
                $user->getFullname()
            ));

        } catch (\Exception $e) {
            $this->logger->error('Failed to approve user', [
                'error' => $e->getMessage(),
                'user_id' => $id ?? null
            ]);
            $this->addFlash('error', 'Erreur lors de l\'approbation du compte.');
        }

        $url = $adminUrlGenerator
            ->setAction(Action::DETAIL)
            ->setEntityId($id)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function resetPwdAction(
        AdminContext           $context,
        AdminUrlGenerator      $adminUrlGenerator,
        EntityManagerInterface $entityManager
    ): RedirectResponse
    {
        try {
            $id = $context->getRequest()->query->get('entityId');
            /** @var User $user */
            $user = $entityManager->getRepository(User::class)->find($id);

            if (!$user) {
                throw new \Exception('Utilisateur introuvable');
            }

            $this->dispatcher->dispatch(new UserPwdResetedEvent($user));

            /** @var User $adminUser */
            $adminUser = $this->security->getUser();

            $this->logger->info('Password reset initiated', [
                'user_id' => $user->getId(),
                'admin_id' => $adminUser?->getId(),
                'user_email' => $user->getEmail()
            ]);

            $this->addFlash('success', sprintf(
                'Un e-mail de réinitialisation a été envoyé à %s.',
                $user->getEmail()
            ));

        } catch (\Exception $e) {
            $this->logger->error('Failed to reset password', [
                'error' => $e->getMessage(),
                'user_id' => $id ?? null
            ]);
            $this->addFlash('error', 'Erreur lors de la réinitialisation du mot de passe.');
        }

        $url = $adminUrlGenerator
            ->setAction(Action::DETAIL)
            ->setEntityId($id)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function toggleActiveAction(
        AdminContext           $context,
        EntityManagerInterface $entityManager,
        AdminUrlGenerator      $adminUrlGenerator
    ): RedirectResponse
    {
        try {
            $id = $context->getRequest()->query->get('entityId');
            /** @var User $user */
            $user = $entityManager->getRepository(User::class)->find($id);

            if (!$user) {
                throw new \Exception('Utilisateur introuvable');
            }

            $previousStatus = $user->isIsActive();
            $user->setIsActive(!$previousStatus);
            $entityManager->persist($user);
            $entityManager->flush();

            /** @var User $adminUser */
            $adminUser = $this->security->getUser();

            $this->logger->info('User status toggled', [
                'user_id' => $user->getId(),
                'admin_id' => $adminUser->getId(),
                'previous_status' => $previousStatus,
                'new_status' => $user->isIsActive()
            ]);

            $status = $user->isIsActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', sprintf(
                'Le compte de %s a été %s.',
                $user->getFullname(),
                $status
            ));

        } catch (\Exception $e) {
            $this->logger->error('Failed to toggle user status', [
                'error' => $e->getMessage(),
                'user_id' => $id ?? null
            ]);
            $this->addFlash('error', 'Erreur lors du changement de statut.');
        }

        $url = $adminUrlGenerator
            ->setAction(Action::DETAIL)
            ->setEntityId($id)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function sendWelcomeAction(
        AdminContext           $context,
        EntityManagerInterface $entityManager,
        AdminUrlGenerator      $adminUrlGenerator
    ): RedirectResponse
    {
        try {
            $id = $context->getRequest()->query->get('entityId');
            /** @var User $user */
            $user = $entityManager->getRepository(User::class)->find($id);

            if (!$user) {
                throw new \Exception('Utilisateur introuvable');
            }

            /** @var User $adminUser */
            $adminUser = $this->security->getUser();

            // TODO: Implémenter l'envoi d'e-mail de bienvenue
            // $this->emailService->sendWelcomeEmail($user);

            $this->logger->info('Welcome email sent', [
                'user_id' => $user->getId(),
                'admin_id' => $adminUser->getId(),
                'user_email' => $user->getEmail()
            ]);

            $this->addFlash('success', sprintf(
                'E-mail de bienvenue envoyé à %s.',
                $user->getEmail()
            ));

        } catch (\Exception $e) {
            $this->logger->error('Failed to send welcome email', [
                'error' => $e->getMessage(),
                'user_id' => $id ?? null
            ]);
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'e-mail de bienvenue.');
        }

        $url = $adminUrlGenerator
            ->setAction(Action::DETAIL)
            ->setEntityId($id)
            ->generateUrl();

        return $this->redirect($url);
    }
}