<?php

namespace App\Controller;

use App\Entity\CheckIn;
use App\Repository\CheckInRepository;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\User;

#[Route('/api/check-in', name: 'api_checkin_')]
class CheckInController extends AbstractController
{
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        #[CurrentUser] User $user,
        SubscriptionRepository $subscriptionRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Check if user has active subscription
        $activeSubscription = $subscriptionRepository->findActiveByUser($user);

        if (!$activeSubscription) {
            return new JsonResponse([
                'error' => 'No active subscription'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        $checkIn = new CheckIn();
        $checkIn->setUser($user);
        $checkIn->setLocation($data['location'] ?? null);
        $checkIn->setNotes($data['notes'] ?? null);

        $entityManager->persist($checkIn);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Check-in successful',
            'checkIn' => [
                'id' => $checkIn->getId(),
                'checkedInAt' => $checkIn->getCheckedInAt()?->format('c'),
                'location' => $checkIn->getLocation(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/today', name: 'today', methods: ['GET'])]
    public function today(
        #[CurrentUser] User $user,
        CheckInRepository $checkInRepository
    ): JsonResponse {
        $todayCheckIns = $checkInRepository->findTodayCheckIns($user);

        $checkInData = array_map(function ($checkIn) {
            return [
                'id' => $checkIn->getId(),
                'checkedInAt' => $checkIn->getCheckedInAt()?->format('c'),
                'checkedOutAt' => $checkIn->getCheckedOutAt()?->format('c'),
                'location' => $checkIn->getLocation(),
            ];
        }, $todayCheckIns);

        return new JsonResponse([
            'checkIns' => $checkInData,
            'total' => count($checkInData)
        ]);
    }

    #[Route('/{id}/checkout', name: 'checkout', methods: ['PATCH'])]
    public function checkout(
        CheckIn $checkIn,
        #[CurrentUser] User $user,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if ($checkIn->getUser() !== $user) {
            return new JsonResponse([
                'error' => 'Unauthorized'
            ], Response::HTTP_FORBIDDEN);
        }

        $checkIn->setCheckedOutAt(new \DateTime());
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Check-out successful',
            'checkIn' => [
                'id' => $checkIn->getId(),
                'checkedInAt' => $checkIn->getCheckedInAt()?->format('c'),
                'checkedOutAt' => $checkIn->getCheckedOutAt()?->format('c'),
            ]
        ]);
    }

    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(
        #[CurrentUser] User $user,
        CheckInRepository $checkInRepository
    ): JsonResponse {
        $checkIns = $checkInRepository->findByUser($user);

        $checkInData = array_map(function ($checkIn) {
            return [
                'id' => $checkIn->getId(),
                'checkedInAt' => $checkIn->getCheckedInAt()?->format('c'),
                'checkedOutAt' => $checkIn->getCheckedOutAt()?->format('c'),
                'location' => $checkIn->getLocation(),
            ];
        }, $checkIns);

        return new JsonResponse([
            'checkIns' => $checkInData,
            'total' => count($checkInData)
        ]);
    }
}

