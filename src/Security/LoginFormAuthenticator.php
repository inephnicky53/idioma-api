<?php

namespace App\Security;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

/**
 * Authenticator for web form login only
 * API login is handled by json_login in security.yaml
 */
class LoginFormAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Only support web form login on the main firewall
        return $request->isMethod('POST') && $request->getPathInfo() === '/';
    }

    public function authenticate(Request $request): Passport
    {
        // Handle web form login
        $email = $request->request->get('email');
        $password = $request->request->get('password');

        if (!$email || !$password) {
            throw new AuthenticationException('Missing email or password');
        }

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return null;
        }

        // Update last login
        $user->setLastLoginAt(new DateTime());
        $this->entityManager->flush();

        // Redirect to admin dashboard
        return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Store error in session for display on login page
        $request->getSession()->set('_security.last_error', $exception);
        $request->getSession()->set('_security.last_username', $request->request->get('email'));

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}

