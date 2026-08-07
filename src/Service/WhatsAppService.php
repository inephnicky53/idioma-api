<?php

namespace App\Service;

use App\Contract\WhatsAppSenderInterface;
use App\Entity\CoursePurchase;
use App\Entity\Subscription;
use App\Entity\User;
use Psr\Log\LoggerInterface;

/**
 * Notifications métier envoyées sur WhatsApp.
 *
 * Chaque méthode traduit un évènement du domaine en appel de template Meta.
 * Les noms de templates sont configurables : ils doivent correspondre
 * exactement à ceux approuvés dans le WhatsApp Business Manager.
 *
 * Toutes les méthodes retournent un booléen plutôt que de lever : l'appelant
 * décide quoi faire d'un échec (repli sur l'email pour l'OTP, simple log ailleurs).
 */
readonly class WhatsAppService
{
    public function __construct(
        private WhatsAppSenderInterface $sender,
        private LoggerInterface         $logger,
        private string                  $otpTemplate,
        private string                  $welcomeTemplate,
        private string                  $subscriptionTemplate,
        private string                  $coursePurchaseTemplate,
        /**
         * Les templates Meta de catégorie AUTHENTICATION embarquent par défaut
         * un bouton « copier le code » qui exige de répéter l'OTP dans un
         * composant `button`. À désactiver si le template n'en a pas.
         */
        private bool                    $otpTemplateHasCopyButton = true,
    ) {}

    public function isConfigured(): bool
    {
        return $this->sender->isConfigured();
    }

    /**
     * Envoie le code de vérification. Canal privilégié à l'inscription.
     */
    public function sendOtp(User $user, string $otp): bool
    {
        $phone = $user->getPhone();
        if (!$phone) {
            $this->logger->info('WhatsApp OTP ignoré : utilisateur sans téléphone', [
                'userId' => $user->getId(),
            ]);

            return false;
        }

        $extraComponents = [];
        if ($this->otpTemplateHasCopyButton) {
            $extraComponents[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $otp]],
            ];
        }

        return $this->sender->sendTemplate($phone, $this->otpTemplate, [$otp], $extraComponents);
    }

    /**
     * Message de bienvenue, envoyé une fois le compte réellement vérifié.
     */
    public function sendWelcome(User $user): bool
    {
        $phone = $user->getPhone();
        if (!$phone) {
            return false;
        }

        return $this->sender->sendTemplate($phone, $this->welcomeTemplate, [
            $this->firstNameOf($user),
        ]);
    }

    /**
     * Confirmation d'activation ou de prolongation d'un abonnement.
     */
    public function sendSubscriptionActivated(Subscription $subscription): bool
    {
        $user = $subscription->getUser();
        $phone = $user?->getPhone();
        if (!$user || !$phone) {
            return false;
        }

        return $this->sender->sendTemplate($phone, $this->subscriptionTemplate, [
            $this->firstNameOf($user),
            $subscription->getPlan()?->getName() ?? 'Idioma',
            $subscription->getEndDate()?->format('d/m/Y') ?? '-',
        ]);
    }

    /**
     * Confirmation d'achat d'un cours.
     */
    public function sendCoursePurchased(CoursePurchase $purchase): bool
    {
        $user = $purchase->getUser();
        $phone = $user?->getPhone();
        if (!$user || !$phone) {
            return false;
        }

        return $this->sender->sendTemplate($phone, $this->coursePurchaseTemplate, [
            $this->firstNameOf($user),
            $purchase->getCourse()?->getTitle() ?? 'votre cours',
        ]);
    }

    /**
     * Meta rejette un paramètre de template vide : on garantit une valeur.
     */
    private function firstNameOf(User $user): string
    {
        $firstName = trim((string) $user->getFirstName());

        return $firstName !== '' ? $firstName : 'cher client';
    }
}
