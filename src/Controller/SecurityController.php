<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly string $appShortName,
        private readonly string $logoPath,
        private readonly string $faviconPath
    )
    {
    }

    #[Route(path: '/', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            // parameters usually defined in Symfony login forms
            'error' => $error,
            'last_username' => $lastUsername,

            // OPTIONAL parameters to customize the login form:
            'translation_domain' => 'admin',
            'favicon_path' => $this->faviconPath,
            'page_title' => 'Connexion',
            'logo' => sprintf('<img src=".%s" alt="%s" style="width: 120px; height: auto;"/>', $this->logoPath, $this->appShortName),
            'csrf_token_intention' => 'authenticate',
            'target_path' => $this->generateUrl('admin'),
            'username_label' => 'Email',
            'password_label' => 'Mot de passe',
            'sign_in_label' => 'Se connecter',
            'username_parameter' => 'username',
            'password_parameter' => 'password',
            'forgot_password_enabled' => false,
            'forgot_password_label' => 'Mot de passe oublié ?',
            'remember_me_enabled' => true,
            'remember_me_parameter' => 'custom_remember_me_param',
            'remember_me_checked' => true,
            'remember_me_label' => 'Se souvenir de moi',
        ]);
    }

    #[Route("connect/google", name: "connect_google_start")]
    public function connectGoogleAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('google')
            ->redirect([], []);
    }

    #[Route("connect/google/check", name: "connect_google_check")]
    public function connectGoogleCheckAction(): void
    {
        // Cette méthode sera interceptée par le guard authenticator
    }

    #[Route("connect/facebook", name: "connect_facebook_start")]
    public function connectFacebookAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('facebook')
            ->redirect(['public_profile', 'email'], []);
    }

    #[Route("connect/facebook/check", name: "connect_facebook_check")]
    public function connectFacebookCheckAction(): void
    {
        // Cette méthode sera interceptée par le guard authenticator
    }

    #[Route(path: '/preview/email', name: 'app_preview_email')]
    public function previewEmail(): Response
    {
        return $this->render('email/validate_teacher.html.twig', [
            'subject' => "Bienvenue sur le site web",
            'user'=> $this->getUser()
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
