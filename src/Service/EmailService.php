<?php

namespace App\Service;

use App\Entity\News;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $appName = 'Idioma Club'
    ) {
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(User $user, string $resetToken): void
    {
        // Build reset link (frontend URL)
        $resetLink = sprintf(
            'http://localhost:3000/reset-password?token=%s',
            $resetToken
        );

        $email = (new Email())
            ->from('noreply@idioma-club.com')
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe - ' . $this->appName)
            ->html($this->renderPasswordResetTemplate($user, $resetLink));

        $this->mailer->send($email);
    }

    /**
     * Send welcome email after registration
     */
    public function sendWelcomeEmail(User $user): void
    {
        $email = (new Email())
            ->from('noreply@idioma-club.com')
            ->to($user->getEmail())
            ->subject('Bienvenue sur ' . $this->appName)
            ->html($this->renderWelcomeTemplate($user));

        $this->mailer->send($email);
    }

    /**
     * Send news announcement to newsletter subscriber
     */
    public function sendNewsEmail(News $news, string $email): void
    {
        $emailMessage = (new Email())
            ->from('noreply@idioma-club.com')
            ->to($email)
            ->subject('Nouvelle annonce : ' . $news->getTitle())
            ->html($this->renderNewsTemplate($news));

        $this->mailer->send($emailMessage);
    }

    /**
     * Render password reset email template
     */
    private function renderPasswordResetTemplate(User $user, string $resetLink): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 0 0 5px 5px; }
        .button { display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Réinitialisation de mot de passe</h1>
        </div>
        <div class="content">
            <p>Bonjour {$user->getFirstName()},</p>
            <p>Vous avez demandé une réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour continuer :</p>
            <a href="{$resetLink}" class="button">Réinitialiser mon mot de passe</a>
            <p>Ce lien expire dans 1 heure.</p>
            <p>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.</p>
            <p>Cordialement,<br>L'équipe {$this->appName}</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 {$this->appName}. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render welcome email template
     */
    private function renderWelcomeTemplate(User $user): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 0 0 5px 5px; }
        .button { display: inline-block; background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bienvenue sur {$this->appName} !</h1>
        </div>
        <div class="content">
            <p>Bonjour {$user->getFirstName()} {$user->getLastName()},</p>
            <p>Merci de vous être inscrit sur {$this->appName}. Votre compte a été créé avec succès.</p>
            <p>Vous pouvez maintenant vous connecter et explorer nos offres d'abonnement.</p>
            <a href="http://localhost:3000/login" class="button">Se connecter</a>
            <p>Si vous avez des questions, n'hésitez pas à nous contacter.</p>
            <p>Cordialement,<br>L'équipe {$this->appName}</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 {$this->appName}. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render news announcement email template
     */
    private function renderNewsTemplate(News $news): string
    {
        $excerpt = $news->getExcerpt() ?? substr(strip_tags($news->getContent()), 0, 200) . '...';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0066cc; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 0 0 5px 5px; }
        .news-title { font-size: 24px; font-weight: bold; margin: 20px 0; color: #0066cc; }
        .news-excerpt { font-size: 16px; margin: 15px 0; color: #555; }
        .button { display: inline-block; background-color: #0066cc; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nouvelle Annonce</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            <div class="news-title">{$news->getTitle()}</div>
            <div class="news-excerpt">{$excerpt}</div>
            <a href="http://localhost:3000/blog" class="button">Lire la suite</a>
            <p style="margin-top: 30px; font-size: 12px; color: #999;">
                Vous recevez cet email car vous êtes abonné à notre newsletter.
            </p>
            <p>Cordialement,<br>L'équipe {$this->appName}</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 {$this->appName}. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}

