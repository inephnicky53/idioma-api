<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Manager\PasswordResetManager;
use App\Trait\FrenchActionsTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action as EasyAdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    use FrenchActionsTrait;

    public function __construct(
        private readonly PasswordResetManager $passwordResetManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $resetPasswordAction = EasyAdminAction::new('resetPassword', 'Réinitialiser mot de passe')
            ->linkToCrudAction('resetPassword')
            ->setIcon('fa fa-key')
            ->displayAsButton();

        $sendPasswordResetEmailAction = EasyAdminAction::new('sendPasswordResetEmail', 'Envoyer email de réinitialisation')
            ->linkToCrudAction('sendPasswordResetEmail')
            ->setIcon('fa fa-envelope')
            ->displayAsButton();

        return $this->configureFrenchActions($actions)
            ->add(Crud::PAGE_INDEX, $resetPasswordAction)
            ->add(Crud::PAGE_INDEX, $sendPasswordResetEmailAction)
            ->add(Crud::PAGE_DETAIL, $resetPasswordAction)
            ->add(Crud::PAGE_DETAIL, $sendPasswordResetEmailAction);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            EmailField::new('email')->setLabel('Email'),
            TextField::new('firstName')->setLabel('Prénom'),
            TextField::new('lastName')->setLabel('Nom'),
            TextField::new('phone')->setLabel('Téléphone'),
            ArrayField::new('roles')->setLabel('Rôles'),
            BooleanField::new('isActive')->setLabel('Actif'),
            BooleanField::new('isEmailVerified')->setLabel('Email vérifié'),
            BooleanField::new('isPhoneVerified')->setLabel('Téléphone vérifié'),
            DateTimeField::new('createdAt')->setLabel('Créé le')->hideOnForm(),
            DateTimeField::new('updatedAt')->setLabel('Modifié le')->hideOnForm(),
            DateTimeField::new('lastLoginAt')->setLabel('Dernière connexion')->hideOnForm(),
        ];
    }

    public function resetPassword(AdminContext $context): Response
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();
        $request = $this->requestStack->getCurrentRequest();

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('new_password');
            if ($newPassword && strlen($newPassword) >= 6) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);

                /** @var EntityManagerInterface $entityManager */
                $entityManager = $this->container->get('doctrine')->getManager();
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Mot de passe réinitialisé avec succès !');

                /** @var AdminUrlGenerator $adminUrlGenerator */
                $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
                return $this->redirect($adminUrlGenerator->setController(self::class)->setAction(EasyAdminAction::DETAIL)->setEntityId($user->getId())->generateUrl());
            } else {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères !');
            }
        }

        return $this->render('@EasyAdmin/crud/reset_password.html.twig', [
            'ea' => $context,
            'entity' => $context->getEntity(),
        ]);
    }

    public function sendPasswordResetEmail(AdminContext $context): RedirectResponse
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();

        $this->passwordResetManager->sendResetPasswordEmail($user->getEmail());

        $this->addFlash('success', 'Email de réinitialisation envoyé !');

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect($adminUrlGenerator->setController(self::class)->setAction(EasyAdminAction::INDEX)->generateUrl());
    }
}

