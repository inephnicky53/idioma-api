<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class RefreshTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UserRepository $userRepository,
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST') && $request->getPathInfo() === '/api/auth/refresh';
    }

    public function authenticate(Request $request): Passport
    {
        $data = json_decode($request->getContent(), true);
        $refreshToken = $data['refreshToken'] ?? null;

        if (!$refreshToken) {
            throw new AuthenticationException('Missing refresh token');
        }

        $user = $this->userRepository->findOneBy(['refreshToken' => $refreshToken]);

        if (!$user) {
            throw new AuthenticationException('Invalid refresh token');
        }

        if ($user->getRefreshTokenExpiresAt() < new \DateTime()) {
            throw new AuthenticationException('Refresh token expired');
        }

        return new SelfValidatingPassport(
            new UserBadge($user->getEmail())
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return null;
        }

        // Generate new JWT token
        $jwtToken = $this->jwtManager->create($user);

        // Generate new refresh token
        $refreshToken = bin2hex(random_bytes(32));
        $user->setRefreshToken($refreshToken);
        $user->setRefreshTokenExpiresAt((new \DateTime())->modify('+30 days'));
        $this->entityManager->flush();

        return new JsonResponse([
            'token' => $jwtToken,
            'refreshToken' => $refreshToken,
        ]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => $exception->getMessageKey()
        ], Response::HTTP_UNAUTHORIZED);
    }
}

