<?php

namespace App\Controller\Api\Teacher;

use App\Entity\User;
use App\Service\Course\TeacherRecordedCourseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/teacher/recorded-courses')]
class TeacherRecordedCourseController extends AbstractController
{
    public function __construct(
        private readonly TeacherRecordedCourseService $courses,
    ) {
    }

    #[Route('', name: 'api_teacher_recorded_courses_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(): JsonResponse
    {
        $teacher = $this->requireTeacher();
        if ($teacher instanceof JsonResponse) {
            return $teacher;
        }

        return $this->json($this->courses->listForTeacher($teacher));
    }

    #[Route('', name: 'api_teacher_recorded_courses_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        return $this->upsert($request, null);
    }

    #[Route('/{id}', name: 'api_teacher_recorded_courses_update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(int $id, Request $request): JsonResponse
    {
        $teacher = $this->requireTeacher();
        if ($teacher instanceof JsonResponse) {
            return $teacher;
        }

        try {
            $course = $this->courses->getOwned($teacher, $id);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        return $this->upsert($request, $course);
    }

    #[Route('/{id}', name: 'api_teacher_recorded_courses_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id): JsonResponse
    {
        $teacher = $this->requireTeacher();
        if ($teacher instanceof JsonResponse) {
            return $teacher;
        }

        try {
            $course = $this->courses->getOwned($teacher, $id);
            $this->courses->delete($teacher, $course);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        return $this->json(['ok' => true]);
    }

    private function upsert(Request $request, mixed $existing): JsonResponse
    {
        $teacher = $this->requireTeacher();
        if ($teacher instanceof JsonResponse) {
            return $teacher;
        }

        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                $payload = [];
            }
            $course = $this->courses->save($teacher, $payload, $existing);
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON invalide.'], Response::HTTP_BAD_REQUEST);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        return $this->json(
            $this->courses->serialize($course, true),
            $existing ? Response::HTTP_OK : Response::HTTP_CREATED
        );
    }

    private function requireTeacher(): mixed
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacher = $user?->getTeacher();
        if (!$teacher) {
            return $this->json(['error' => 'Seul un professeur peut publier un cours.'], Response::HTTP_FORBIDDEN);
        }

        return $teacher;
    }
}
