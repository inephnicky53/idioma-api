<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/auth/profile', name: 'api_update_profile', methods: ['PATCH'])]
class UpdateProfileAction extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function __invoke(
        Request $request,
        #[CurrentUser] ?User $user
    ): Response {
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        // Récupérer les données du formulaire (FormData)
        $firstName = $request->request->get('firstName');
        $lastName = $request->request->get('lastName');
        $phone = $request->request->get('phone');
        $currentPassword = $request->request->get('currentPassword');
        $newPassword = $request->request->get('newPassword');

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
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Retourner la réponse avec l'utilisateur mis à jour
        return $this->json([
            'success' => true,
            'user' => $user,
        ], Response::HTTP_OK, [], [
            'groups' => ['user:read'],
        ]);
    }
}

