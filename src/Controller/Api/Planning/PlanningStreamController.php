<?php

namespace App\Controller\Api\Planning;

use App\Entity\Planning;
use App\Entity\User;
use App\Repository\PlanningRepository;
use App\Service\FeatureFlags;
use App\Service\Streaming\GetStreamService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        #[Autowire('%app.frontend_url%')]
        private readonly string $frontendUrl,
    ) {
    }

    /**
     * Create (or reuse) a GetStream video room for a planning and return join credentials.
     */
    #[Route('/{id}/stream', name: 'api_planning_stream', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function stream(int $id): JsonResponse
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
        $planning = $this->planningRepository->find($id);

        if (!$planning) {
            return $this->json(['error' => 'Planning not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('PLANNING_MANAGE', $planning)) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $teacherUser = $planning->getTeacher()?->getUser();
        $isHost = $teacherUser && $teacherUser->getId() === $user->getId();

        $memberIds = [];
        if ($teacherUser) {
            $memberIds[] = (string) $teacherUser->getId();
        }
        foreach ($planning->getParticipants() as $participant) {
            $memberIds[] = (string) $participant->getId();
        }
        $memberIds = array_values(array_unique($memberIds));

        $participantCount = count($memberIds);
        $callType = $participantCount > 2 ? 'livestream' : 'default';
        $callId = 'planning-' . $planning->getId();
        $hostUserId = $teacherUser
            ? (string) $teacherUser->getId()
            : (string) $user->getId();

        try {
            $credentials = $this->getStreamService->prepareCall(
                $callId,
                $callType,
                (string) $user->getId(),
                $memberIds,
                $isHost,
            );
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        $locale = 'fr';
        $meetingPath = "/{$locale}/dshb-live/{$planning->getId()}";
        $planning->setMeetingLink(rtrim($this->frontendUrl, '/') . $meetingPath);

        if ($planning->getStatus() === Planning::STATUS_CREATED) {
            $planning->setStatus(Planning::STATUS_STARTED);
        }

        $this->planningRepository->getEntityManager()->flush();

        return $this->json([
            ...$credentials,
            'planningId' => $planning->getId(),
            'meetingLink' => $planning->getMeetingLink(),
        ]);
    }
}
