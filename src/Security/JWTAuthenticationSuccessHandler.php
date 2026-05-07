<?php

namespace App\Security;

use App\Entity\RefreshToken;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
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
        private RefreshTokenManagerInterface $refreshTokenManager,
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return null;
        }

        // Générer le JWT token (les données utilisateur seront ajoutées par JWTCreatedListener)
        $jwtToken = $this->jwtManager->create($user);

        // Créer un refresh token
        $refreshToken = new RefreshToken();
        $refreshToken->setUsername($user->getEmail());
        $refreshToken->setRefreshToken();
        $refreshToken->setValid(new DateTime('+30 days'));

        $this->refreshTokenManager->save($refreshToken);

        return new JsonResponse([
            'token' => $jwtToken,
            'refreshToken' => $refreshToken->getRefreshToken(),
        ]);
    }
}

