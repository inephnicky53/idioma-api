<?php

namespace App\Service;

use App\Entity\News;
use App\Entity\Payment;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $appEmailNoReply,
        private readonly string $frontendUrl,
        private readonly string $appName = 'Idioma'
    ) {
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(User $user, string $resetToken): void
    {
        $resetLink = sprintf('%s/reset-password?token=%s', $this->frontendUrl, $resetToken);

        $email = (new Email())
            ->from($this->appEmailNoReply)
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe - ' . $this->appName)
            ->html($this->twig->render('emails/password_reset.html.twig', [
                'user' => $user,
                'resetLink' => $resetLink,
                'appName' => $this->appName,
            ]));

        $this->mailer->send($email);
    }

    /**
     * Send welcome email after registration
     */
    public function sendWelcomeEmail(User $user, string $verificationToken): void
    {
        $verificationLink = sprintf('%s/verify-email?token=%s', $this->frontendUrl, $verificationToken);
        
        $email = (new Email())
            ->from($this->appEmailNoReply)
            ->to($user->getEmail())
            ->subject('Bienvenue sur ' . $this->appName)
            ->html($this->twig->render('emails/verify_email.html.twig', [
                'user' => $user,
                'verificationLink' => $verificationLink,
                'appName' => $this->appName,
            ]));

        $this->mailer->send($email);
    }

    /**
     * Send news announcement to newsletter subscriber
     */
    public function sendNewsEmail(News $news, string $emailAddress): void
    {
        $email = (new Email())
            ->from($this->appEmailNoReply)
            ->to($emailAddress)
            ->subject('Nouvelle annonce : ' . $news->getTitle())
            ->html($this->twig->render('emails/news.html.twig', [
                'news' => $news,
                'appName' => $this->appName,
                'frontendUrl' => $this->frontendUrl,
            ]));

        $this->mailer->send($email);
    }

    /**
     * Send OTP email
     */
    public function sendOtpEmail(User $user, string $otp): void
    {
        $email = (new Email())
            ->from($this->appEmailNoReply)
            ->to($user->getEmail())
            ->subject('Votre code de vérification - ' . $this->appName)
            ->html($this->twig->render('emails/otp.html.twig', [
                'user' => $user,
                'otp' => $otp,
                'appName' => $this->appName,
            ]));

        $this->mailer->send($email);
    }

    /**
     * Send payment receipt email
     */
    public function sendPaymentReceiptEmail(Payment $payment): void
    {
        $user = $payment->getUser();
        if (!$user) {
            return;
        }

        $email = (new Email())
            ->from($this->appEmailNoReply)
            ->to($user->getEmail())
            ->subject('Reçu de paiement - ' . $this->appName)
            ->html($this->twig->render('emails/payment_receipt.html.twig', [
                'payment' => $payment,
                'user' => $user,
                'appName' => $this->appName,
            ]));

        $this->mailer->send($email);
    }
}
