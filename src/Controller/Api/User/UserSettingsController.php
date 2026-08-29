<?php

declare(strict_types=1);

namespace App\Controller\Api\User;

use App\Entity\User;
use App\Service\User\UserSettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/user/settings')]
#[IsGranted('ROLE_USER')]
class UserSettingsController extends AbstractController
{
    public function __construct(
        private readonly UserSettingsService $settingsService,
    ) {
    }

    #[Route('/profile', name: 'api_user_settings_profile', methods: ['PATCH'])]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true) ?: [];

        try {
            $updated = $this->settingsService->updateProfile($user, $payload);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return $this->json([
            'message' => 'Profil mis à jour.',
            'user' => $this->serializeUser($updated),
        ]);
    }

    #[Route('/phone/request', name: 'api_user_settings_phone_request', methods: ['POST'])]
    public function requestPhoneChange(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true) ?: [];
        $phone = trim((string) ($payload['phone'] ?? ''));

        if ($phone === '') {
            return $this->json(['message' => 'Numéro de téléphone requis.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->settingsService->requestPhoneChange($user, $phone);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return $this->json($result);
    }

    #[Route('/phone/confirm', name: 'api_user_settings_phone_confirm', methods: ['POST'])]
    public function confirmPhoneChange(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true) ?: [];
        $code = trim((string) ($payload['code'] ?? ''));

        if ($code === '') {
            return $this->json(['message' => 'Code de vérification requis.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $updated = $this->settingsService->confirmPhoneChange($user, $code);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return $this->json([
            'message' => 'Numéro de téléphone mis à jour.',
            'user' => $this->serializeUser($updated),
        ]);
    }

    #[Route('/email/request', name: 'api_user_settings_email_request', methods: ['POST'])]
    public function requestEmailChange(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true) ?: [];
        $email = trim((string) ($payload['email'] ?? ''));

        if ($email === '') {
            return $this->json(['message' => 'Adresse e-mail requise.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->settingsService->requestEmailChange($user, $email);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return $this->json($result);
    }

    #[Route('/email/confirm', name: 'api_user_settings_email_confirm', methods: ['POST'])]
    public function confirmEmailChange(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true) ?: [];
        $code = trim((string) ($payload['code'] ?? ''));

        if ($code === '') {
            return $this->json(['message' => 'Code de vérification requis.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $updated = $this->settingsService->confirmEmailChange($user, $code);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return $this->json([
            'message' => 'Adresse e-mail mise à jour.',
            'user' => $this->serializeUser($updated),
        ]);
    }

    #[Route('/password', name: 'api_user_settings_password', methods: ['POST'])]
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true) ?: [];
        $current = (string) ($payload['currentPassword'] ?? '');
        $next = (string) ($payload['newPassword'] ?? '');

        if ($current === '' || $next === '') {
            return $this->json(['message' => 'Mot de passe actuel et nouveau mot de passe requis.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->settingsService->changePassword($user, $current, $next);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return $this->json(['message' => 'Mot de passe mis à jour.']);
    }

    #[Route('/notifications', name: 'api_user_settings_notifications', methods: ['PATCH'])]
    public function updateNotifications(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true) ?: [];

        try {
            $updated = $this->settingsService->updateNotifications($user, $payload);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return $this->json([
            'message' => 'Préférences enregistrées.',
            'notificationPreferences' => $updated->getNotificationPreferences(),
        ]);
    }

    #[Route('/close-account', name: 'api_user_settings_close_account', methods: ['POST'])]
    public function closeAccount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true) ?: [];
        $password = (string) ($payload['password'] ?? '');

        if ($password === '') {
            return $this->json(['message' => 'Mot de passe requis.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->settingsService->closeAccount($user, $password);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return $this->json(['message' => 'Votre compte a été fermé.']);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'firstname' => $user->getFirstname(),
            'name' => $user->getName(),
            'country' => $user->getCountry(),
            'birthdayAt' => $user->getBirthdayAt()?->format('Y-m-d'),
            'isVerified' => $user->isVerified(),
            'isPhoneVerified' => $user->isIsPhoneVerified(),
            'notificationPreferences' => $user->getNotificationPreferences(),
        ];
    }
}
