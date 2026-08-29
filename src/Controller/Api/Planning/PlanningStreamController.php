<?php

namespace App\Controller\Api\Planning;

use App\Entity\Planning;
use App\Entity\User;
use App\Repository\PlanningRepository;
use App\Service\FeatureFlags;
use App\Service\Inbox\SalonThreadService;
use App\Service\Planning\PlanningAttendanceService;
use App\Service\Streaming\GetStreamService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/plannings')]
class PlanningStreamController extends AbstractController
{
    public function __construct(
        private readonly PlanningRepository $planningRepository,
        private readonly GetStreamService $getStreamService,
        private readonly FeatureFlags $featureFlags,
        private readonly SalonThreadService $salonThreads,
        private readonly PlanningAttendanceService $attendance,
        #[Autowire('%app.frontend_url%')]
        private readonly string $frontendUrl,
    ) {
    }

    /**
     * Create (or reuse) a GetStream video room for a planning and return join credentials.
     */
    #[Route('/{id}/stream', name: 'api_planning_stream', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createStream(int $id, Request $request): JsonResponse
    {
        if (!$this->featureFlags->isEnabled('livestreaming')) {
            return $this->json(['error' => 'Livestreaming is disabled on this platform.'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->getStreamService->isConfigured()) {
            return $this->json(
                ['error' => 'GetStream is not configured. Set GETSTREAM_API_KEY and GETSTREAM_API_SECRET.'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        /** @var User $user */
        $user = $this->getUser();
        $planning = $this->requirePlanning($id);
        if (!$planning) {
            return $this->json(['error' => 'Planning not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('PLANNING_MANAGE', $planning)) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        if (in_array($planning->getStatus(), [Planning::STATUS_CANCELED, Planning::STATUS_REJECTED], true)) {
            return $this->json(['error' => 'Cette séance a été annulée.'], Response::HTTP_CONFLICT);
        }

        $teacherUser = $planning->getTeacher()?->getUser();
        $isHost = $teacherUser && $teacherUser->getId() === $user->getId();
        $hostUserId = $teacherUser ? (string) $teacherUser->getId() : (string) $user->getId();

        $memberIds = [];
        $userNames = [];
        if ($teacherUser) {
            $memberIds[] = (string) $teacherUser->getId();
            $userNames[(string) $teacherUser->getId()] = $teacherUser->getFullname() ?: $teacherUser->getEmail();
        }
        foreach ($planning->getParticipants() as $participant) {
            $memberIds[] = (string) $participant->getId();
            $userNames[(string) $participant->getId()] = $participant->getFullname() ?: $participant->getEmail();
        }
        $memberIds = array_values(array_unique($memberIds));

        $isSalon = count($memberIds) > 2;
        $callType = 'default';
        $callId = 'planning-' . $planning->getId();

        try {
            $credentials = $this->getStreamService->prepareCall(
                $callId,
                $callType,
                $hostUserId,
                $memberIds,
                (string) $user->getId(),
                $isHost,
                $userNames,
            );
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        $locale = $request->getLocale() ?: 'fr';
        $meetingPath = "/{$locale}/dshb-live/{$planning->getId()}";
        $planning->setMeetingLink(rtrim($this->frontendUrl, '/') . $meetingPath);

        if ($planning->getStatus() === Planning::STATUS_CREATED) {
            $planning->setStatus(Planning::STATUS_STARTED);
        }

        $thread = $this->salonThreads->findOrCreateForPlanning($planning);
        $this->planningRepository->getEntityManager()->flush();

        return $this->json([
            ...$credentials,
            'planningId' => $planning->getId(),
            'meetingLink' => $planning->getMeetingLink(),
            'isHost' => $isHost,
            'isSalon' => $isSalon,
            'threadId' => $thread->getId(),
            'title' => $planning->getCourse()?->getTitle()
                ?: ($isSalon ? 'Salon' : 'Cours particulier'),
            'start' => $planning->getStart()?->format(\DateTimeInterface::ATOM),
            'end' => $planning->getEnd()?->format(\DateTimeInterface::ATOM),
            'displayName' => $user->getFullname() ?: $user->getEmail(),
            'participants' => $this->participantPayload($planning),
        ]);
    }

    #[Route('/{id}/stream/end', name: 'api_planning_stream_end', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function endStream(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $planning = $this->requirePlanning($id);
        if (!$planning) {
            return $this->json(['error' => 'Planning not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($planning->getTeacher()?->getUser() !== $user) {
            return $this->json(['error' => 'Seul le professeur peut clôturer la séance.'], Response::HTTP_FORBIDDEN);
        }

        $planning->setStatus(Planning::STATUS_FINISHED);
        try {
            $this->getStreamService->endCall(
                'planning-' . $planning->getId(),
                'default',
                (string) $user->getId(),
            );
        } catch (\Throwable) {
            // Ending the DB session still succeeds if Stream is unreachable.
        }
        $this->planningRepository->getEntityManager()->flush();

        return $this->json([
            'planningId' => $planning->getId(),
            'status' => $planning->getStatus(),
        ]);
    }

    #[Route('/{id}/attendance', name: 'api_planning_attendance_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listAttendance(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $planning = $this->requirePlanning($id);
        if (!$planning) {
            return $this->json(['error' => 'Planning not found.'], Response::HTTP_NOT_FOUND);
        }
        if (!$this->isGranted('PLANNING_MANAGE', $planning)) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $isHost = $planning->getTeacher()?->getUser() === $user;

        return $this->json([
            'isHost' => $isHost,
            'roster' => $this->attendance->roster($planning, $user, $isHost),
        ]);
    }

    #[Route('/{id}/attendance', name: 'api_planning_attendance_report', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reportAttendance(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $planning = $this->requirePlanning($id);
        if (!$planning) {
            return $this->json(['error' => 'Planning not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                $payload = [];
            }
            $row = $this->attendance->report($planning, $user, $payload);
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON invalide.'], Response::HTTP_BAD_REQUEST);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        }

        return $this->json($row);
    }

    private function requirePlanning(int $id): ?Planning
    {
        return $this->planningRepository->find($id);
    }

    /** @return list<array{id: int|null, name: string|null, role: string}> */
    private function participantPayload(Planning $planning): array
    {
        $out = [];
        $teacherUser = $planning->getTeacher()?->getUser();
        if ($teacherUser) {
            $out[] = [
                'id' => $teacherUser->getId(),
                'name' => $teacherUser->getFullname() ?: $teacherUser->getEmail(),
                'role' => 'teacher',
            ];
        }
        foreach ($planning->getParticipants() as $participant) {
            $out[] = [
                'id' => $participant->getId(),
                'name' => $participant->getFullname() ?: $participant->getEmail(),
                'role' => 'student',
            ];
        }

        return $out;
    }
}
