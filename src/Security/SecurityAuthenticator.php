<?php

namespace App\Security;

use App\Entity\User;
use App\Service\GeoIP;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class SecurityAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager
    )
    {
    }

    public function authenticate(Request $request): Passport
    {
        $isLocal = in_array($request->getClientIp(), ['127.0.0.1', '::1']);
        $ip = $isLocal ? '41.78.192.90' : $request->getClientIp();
        $country = $isLocal ? "CD" : GeoIP::check($ip)->countryCode ?? 'CD';

        $username = $request->request->get('username', '');
        $prefix = GeoIP::countryPrefix($country);
        $pattern = "/^(\+$prefix|0|00$prefix)([0-9]{4,15})$/";
        //dd($pattern, $country);
        if (preg_match($pattern, $username))
            $username = preg_replace($pattern, "$prefix$2", $username);

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)
            ->findByPhoneOrUsername($username);

        if (!$user)
            throw new CustomUserMessageAuthenticationException("Identifiants invalides.");

        $this->lastPassport = new Passport(
            new UserBadge($user->getEmail()),
            new PasswordCredentials($request->request->get('password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
            ]
        );

        return $this->lastPassport;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();
        $user->setLastLoginAt(new \DateTimeImmutable());
        $user->setLastLoginIp($request->getClientIp());

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('admin'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
