<?php

namespace App\Security;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class JWTAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $entityManager,
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return null;
        }

        // Générer le JWT token (les données utilisateur seront ajoutées par JWTCreatedListener)
        $jwtToken = $this->jwtManager->create($user);

        // Refresh token unifié sur le champ User.refreshToken, cohérent avec
        // RefreshTokenAuthenticator (/api/auth/refresh). Auparavant le login créait
        // un token Gesdinet jamais retrouvé par le refresh -> le refresh était cassé.
        $refreshToken = bin2hex(random_bytes(32));
        $user->setRefreshToken($refreshToken);
        $user->setRefreshTokenExpiresAt(new DateTime('+30 days'));
        $this->entityManager->flush();

        return new JsonResponse([
            'token' => $jwtToken,
            'refreshToken' => $refreshToken,
        ]);
    }
}

