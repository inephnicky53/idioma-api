<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/auth/profile', name: 'api_update_profile', methods: ['PATCH'])]
class UpdateProfileAction extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(
        Request $request,
        #[CurrentUser] ?User $user
    ): Response {
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        // Récupérer l'utilisateur depuis la base de données pour s'assurer qu'il est attaché à la session Doctrine
        $user = $this->entityManager->getRepository(User::class)->find($user->getId());
        if (!$user) {
            throw $this->createAccessDeniedException('Utilisateur introuvable');
        }

        $this->logger->info('UpdateProfile called', [
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        // Récupérer les données du formulaire (FormData)
        $firstName = $request->request->get('firstName');
        $lastName = $request->request->get('lastName');
        $phone = $request->request->get('phone');
        $currentPassword = $request->request->get('currentPassword');
        $newPassword = $request->request->get('newPassword');

        $this->logger->info('UpdateProfile data received', [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'phone' => $phone,
            'hasCurrentPassword' => !empty($currentPassword),
            'hasNewPassword' => !empty($newPassword),
        ]);

        // Mettre à jour les champs simples
        if ($firstName !== null && $firstName !== '') {
            $user->setFirstName($firstName);
        }

        if ($lastName !== null && $lastName !== '') {
            $user->setLastName($lastName);
        }

        if ($phone !== null) {
            $user->setPhone($phone === '' ? null : $phone);
        }

        // Gérer le changement de mot de passe
        if ($newPassword !== null && $newPassword !== '') {
            // Vérifier que le mot de passe actuel est fourni
            if (!$currentPassword) {
                throw new BadRequestHttpException('Le mot de passe actuel est requis pour changer le mot de passe');
            }

            // Vérifier que le mot de passe actuel est correct
            if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                throw new BadRequestHttpException('Le mot de passe actuel est incorrect');
            }

            // Hacher et définir le nouveau mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
        }

        // Sauvegarder les modifications
        $this->logger->info('UpdateProfile before persist', [
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'phone' => $user->getPhone(),
        ]);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->logger->info('UpdateProfile after flush', [
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'phone' => $user->getPhone(),
        ]);

        // Retourner la réponse avec l'utilisateur mis à jour
        return $this->json([
            'success' => true,
            'user' => $user,
        ], Response::HTTP_OK, [], [
            'groups' => ['user:read'],
        ]);
    }
}

