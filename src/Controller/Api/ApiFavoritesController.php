<?php

namespace App\Controller\Api;

use App\Entity\Teacher;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Per-user favorite ("liked") teachers, synced across devices.
 */
#[IsGranted('ROLE_USER')]
class ApiFavoritesController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    private function ids(User $user): array
    {
        return array_values(array_map(
            static fn (Teacher $t) => $t->getId(),
            $user->getFavoriteTeachers()->toArray()
        ));
    }

    #[Route('/api/user/favorites', name: 'api_user_favorites', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse(['ids' => $this->ids($user)]);
    }

    #[Route('/api/user/favorites/toggle', name: 'api_user_favorites_toggle', methods: ['POST'])]
    public function toggle(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $teacherId = $request->toArray()['teacherId'] ?? null;
        if (!$teacherId) {
            return new JsonResponse(['error' => 'teacherId requis'], Response::HTTP_BAD_REQUEST);
        }

        $teacher = $this->em->getRepository(Teacher::class)->find($teacherId);
        if (!$teacher) {
            return new JsonResponse(['error' => 'Professeur introuvable'], Response::HTTP_NOT_FOUND);
        }

        if ($user->hasFavoriteTeacher($teacher)) {
            $user->removeFavoriteTeacher($teacher);
            $favorited = false;
        } else {
            $user->addFavoriteTeacher($teacher);
            $favorited = true;
        }
        $this->em->flush();

        return new JsonResponse(['favorited' => $favorited, 'ids' => $this->ids($user)]);
    }
}
