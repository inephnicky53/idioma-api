<?php

namespace App\Controller\Api\Planning;

use App\Entity\Course;
use App\Entity\User;
use App\Exception\InsufficientHoursException;
use App\Repository\CourseRepository;
use App\Service\Planning\PlanningManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/plannings')]
class PlanningOrganizeController extends AbstractController
{
    public function __construct(
        private readonly PlanningManager $planningManager,
        private readonly CourseRepository $courses,
    ) {
    }

    /**
     * Teacher schedules a 1:1 or salon (multi-student) live session.
     */
    #[Route('/organize', name: 'api_planning_organize', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function organize(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->getTeacher()) {
            return $this->json(['error' => 'Seul un professeur peut organiser une séance.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                $payload = [];
            }

            $startRaw = (string) ($payload['start'] ?? '');
            try {
                $start = $startRaw !== '' ? new \DateTimeImmutable($startRaw) : null;
            } catch (\Exception) {
                return $this->json(['error' => 'Date invalide.'], Response::HTTP_BAD_REQUEST);
            }
            if (!$start) {
                return $this->json(['error' => 'La date de début est requise.'], Response::HTTP_BAD_REQUEST);
            }

            $end = null;
            if (!empty($payload['end'])) {
                try {
                    $end = new \DateTimeImmutable((string) $payload['end']);
                } catch (\Exception) {
                    return $this->json(['error' => 'Date de fin invalide.'], Response::HTTP_BAD_REQUEST);
                }
            }
            $studentIds = $payload['studentIds'] ?? $payload['participants'] ?? [];
            if (!is_array($studentIds)) {
                $studentIds = [];
            }

            $course = null;
            $courseId = (int) ($payload['courseId'] ?? 0);
            if ($courseId > 0) {
                $course = $this->courses->find($courseId);
                if (!$course instanceof Course || $course->getTeacher()?->getUser() !== $user) {
                    return $this->json(['error' => 'Cours introuvable.'], Response::HTTP_BAD_REQUEST);
                }
            }

            $planning = $this->planningManager->organize($start, $studentIds, $end, $course);
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON invalide.'], Response::HTTP_BAD_REQUEST);
        } catch (InsufficientHoursException $e) {
            return $this->json(['error' => $e->getMessage(), 'code' => 'INSUFFICIENT_HOURS'], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $participants = [];
        foreach ($planning->getParticipants() as $participant) {
            $participants[] = [
                'id' => $participant->getId(),
                'name' => $participant->getFullname() ?: $participant->getEmail(),
            ];
        }

        return $this->json([
            'id' => $planning->getId(),
            'start' => $planning->getStart()?->format(\DateTimeInterface::ATOM),
            'end' => $planning->getEnd()?->format(\DateTimeInterface::ATOM),
            'status' => $planning->getStatus(),
            'isSalon' => $planning->isSalon(),
            'participants' => $participants,
        ], Response::HTTP_CREATED);
    }
}
