<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\OTP;
use App\Entity\User;
use App\Repository\OTPRepository;
use App\Repository\UserRepository;
use App\Sender\EmailSender;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use function Symfony\Component\String\u;

class UserSettingsService
{
    private const OTP_EXPIRY_MINUTES = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OTPRepository $otpRepository,
        private readonly UserRepository $userRepository,
        private readonly SmsService $smsService,
        private readonly EmailSender $emailSender,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function updateProfile(User $user, array $payload): User
    {
        if (isset($payload['firstname'])) {
            $user->setFirstname(trim((string) $payload['firstname']));
        }
        if (isset($payload['name'])) {
            $user->setName(trim((string) $payload['name']));
        }
        if (array_key_exists('country', $payload)) {
            $country = trim((string) ($payload['country'] ?? ''));
            $user->setCountry($country !== '' ? $country : null);
        }
        if (array_key_exists('birthdayAt', $payload)) {
            $raw = trim((string) ($payload['birthdayAt'] ?? ''));
            if ($raw === '') {
                $user->setBirthdayAt(null);
            } else {
                $date = \DateTime::createFromFormat('Y-m-d', $raw);
                if (!$date) {
                    throw new BadRequestHttpException('Date de naissance invalide (format attendu : AAAA-MM-JJ).');
                }
                $user->setBirthdayAt($date);
            }
        }

        $this->em->flush();

        return $user;
    }

    public function requestPhoneChange(User $user, string $newPhone): array
    {
        $normalized = $this->normalizePhone($newPhone);
        if ($normalized === $user->getPhone()) {
            throw new BadRequestHttpException('Ce numéro est déjà associé à votre compte.');
        }

        $existing = $this->userRepository->findOneBy(['phone' => $normalized]);
        if ($existing && $existing->getId() !== $user->getId()) {
            throw new ConflictHttpException('Ce numéro est déjà utilisé par un autre compte.');
        }

        $this->otpRepository->deleteBy($user, OTP::TYPE_PHONE_CHANGE);
        $otp = OTP::generate(
            $user,
            4,
            self::OTP_EXPIRY_MINUTES,
            OTP::TYPE_PHONE_CHANGE,
            $normalized,
            $user->getId()
        );
        $this->otpRepository->add($otp, true);

        $message = sprintf('Votre code Idioma pour confirmer votre numéro : %s', $otp->getPass());
        $this->smsService->sendBc('+' . ltrim($normalized, '+'), $message);

        return [
            'status' => true,
            'message' => 'Un code de vérification a été envoyé par SMS.',
            'maskedPhone' => $this->maskPhone($normalized),
        ];
    }

    public function confirmPhoneChange(User $user, string $code): User
    {
        $otp = $this->findValidOtp($user, $code, OTP::TYPE_PHONE_CHANGE);
        $newPhone = $otp->getPhone();
        if (!$newPhone) {
            throw new BadRequestHttpException('Aucun numéro en attente de validation.');
        }

        $existing = $this->userRepository->findOneBy(['phone' => $newPhone]);
        if ($existing && $existing->getId() !== $user->getId()) {
            throw new ConflictHttpException('Ce numéro est déjà utilisé par un autre compte.');
        }

        $user->setPhone($newPhone);
        $user->setIsPhoneVerified(true);
        $this->otpRepository->remove($otp, true);
        $this->em->flush();

        return $user;
    }

    public function requestEmailChange(User $user, string $newEmail): array
    {
        $newEmail = strtolower(trim($newEmail));
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Adresse e-mail invalide.');
        }
        if ($newEmail === strtolower((string) $user->getEmail())) {
            throw new BadRequestHttpException('Cette adresse e-mail est déjà associée à votre compte.');
        }

        $existing = $this->userRepository->findOneBy(['email' => $newEmail]);
        if ($existing && $existing->getId() !== $user->getId()) {
            throw new ConflictHttpException('Cette adresse e-mail est déjà utilisée par un autre compte.');
        }

        $this->otpRepository->deleteBy($user, OTP::TYPE_EMAIL_CHANGE);
        $otp = OTP::generate(
            $user,
            4,
            self::OTP_EXPIRY_MINUTES,
            OTP::TYPE_EMAIL_CHANGE,
            $newEmail,
            $user->getId()
        );
        $this->otpRepository->add($otp, true);

        $this->emailSender->send(
            'Confirmez votre nouvelle adresse e-mail',
            $newEmail,
            'email/otp_registration.mjml.twig',
            [
                'user' => $user,
                'otp' => $otp->getPass(),
                'expiry_minutes' => self::OTP_EXPIRY_MINUTES,
            ]
        );

        return [
            'status' => true,
            'message' => 'Un code de vérification a été envoyé par e-mail.',
            'maskedEmail' => $this->maskEmail($newEmail),
        ];
    }

    public function confirmEmailChange(User $user, string $code): User
    {
        $otp = $this->findValidOtp($user, $code, OTP::TYPE_EMAIL_CHANGE);
        $newEmail = $otp->getPhone();
        if (!$newEmail || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Aucune adresse e-mail en attente de validation.');
        }

        $existing = $this->userRepository->findOneBy(['email' => $newEmail]);
        if ($existing && $existing->getId() !== $user->getId()) {
            throw new ConflictHttpException('Cette adresse e-mail est déjà utilisée par un autre compte.');
        }

        $user->setEmail($newEmail);
        $user->setIsVerified(true);
        $user->setConfirmationToken(null);
        $this->otpRepository->remove($otp, true);
        $this->em->flush();

        return $user;
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (strlen($newPassword) < 8) {
            throw new BadRequestHttpException('Le mot de passe doit contenir au moins 8 caractères.');
        }
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new BadRequestHttpException('Mot de passe actuel incorrect.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->em->flush();
    }

    public function updateNotifications(User $user, array $payload): User
    {
        $allowed = array_keys(User::defaultNotificationSettings());
        $current = $user->getNotificationSettings();
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $current[$key] = (bool) $payload[$key];
            }
        }
        $user->setNotificationSettings($current);

        if (array_key_exists('newsletter', $payload)) {
            $user->setIsNewsletterSubscribed((bool) $payload['newsletter']);
        }

        $this->em->flush();

        return $user;
    }

    public function closeAccount(User $user, string $password): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new BadRequestHttpException('Mot de passe incorrect.');
        }

        $user->setIsActive(false);
        if ($teacher = $user->getTeacher()) {
            $teacher->setIsActive(false);
        }
        $this->em->flush();
    }

    private function findValidOtp(User $user, string $code, string $type): OTP
    {
        $otp = $this->otpRepository->findOneBy([
            'user' => $user,
            'pass' => trim($code),
            'type' => $type,
        ]);
        if (!$otp) {
            throw new BadRequestHttpException('Code de vérification invalide.');
        }
        if ($otp->isExpired()) {
            $this->otpRepository->remove($otp, true);
            throw new BadRequestHttpException('Code de vérification expiré.');
        }

        return $otp;
    }

    private function normalizePhone(string $phone): string
    {
        return u($phone)
            ->trim()
            ->replaceMatches('/\s+/', '')
            ->trimPrefix('+')
            ->toString();
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) <= 4) {
            return '****';
        }

        return str_repeat('*', max(0, strlen($phone) - 4)) . substr($phone, -4);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        if ($local === '' || $domain === '') {
            return '***';
        }
        $visible = substr($local, 0, min(2, strlen($local)));

        return $visible . '***@' . $domain;
    }
}
