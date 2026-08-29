<?php

namespace App\Service\User;

use App\Dto\ResetPasswordInput;
use App\Dto\ResetRequestedInput;
use App\Entity\OTP;
use App\Entity\User;
use App\Event\ResetPasswordEvent;
use App\Exception\UserNotFoundException;
use App\Repository\OTPRepository;
use App\Repository\UserRepository;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use function Symfony\Component\String\u;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class UserManager
{
    public function __construct(
        private readonly UserRepository              $userRepository,
        private readonly OTPRepository               $OTPRepository,
        private readonly SmsService                  $smsService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface    $jwtManager,
        private readonly Security                    $security,
        private readonly MailerInterface             $mailer,
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface    $dispatcher,
        private readonly LoggerInterface             $logger,
    )
    {
    }

    public function resetRequested(ResetRequestedInput $dto): JsonResponse
    {
        $type = $this->normalizeResetType($dto->type);
        $user = $this->findUserForReset($type, $dto->value);

        $genericMessage = 'Si un compte existe, un code de réinitialisation a été envoyé.';

        if (!$user) {
            return new JsonResponse(['message' => $genericMessage]);
        }

        $this->OTPRepository->deleteBy($user, OTP::TYPE_RESET_PASSWORD);

        $contact = $type === 'TYPE_EMAIL' ? '' : (string) $user->getPhone();
        $otp = OTP::generate($user, 4, 15, OTP::TYPE_RESET_PASSWORD, $contact, $user->getId());

        $this->em->persist($otp);
        $this->em->flush();

        if ($type === 'TYPE_PHONE') {
            $message = "Votre code de réinitialisation est : {$otp->getPass()}";
            try {
                $this->smsService->sendBc($user->getPhone(true), $message);
            } catch (\Throwable $e) {
                $this->logger->error('Erreur envoi SMS reset password', [
                    'user_id' => $user->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($type === 'TYPE_EMAIL') {
            try {
                $subject = 'Demande de réinitialisation de mot de passe';
                $email = (new TemplatedEmail())
                    ->to(new Address($user->getEmail()))
                    ->subject($subject)
                    ->htmlTemplate('user/email/reinitialisation.mjml.twig')
                    ->context([
                        'user' => $user,
                        'subject' => $subject,
                        'otp' => $otp,
                    ]);

                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                $this->logger->error('Erreur envoi email reset password', [
                    'user_id' => $user->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new JsonResponse([
            'message' => $genericMessage,
            'token' => $user->getEmail(),
        ]);
    }

    private function normalizeResetType(?string $type): ?string
    {
        $normalized = strtoupper(trim((string) $type));

        return match ($normalized) {
            'EMAIL', 'TYPE_EMAIL' => 'TYPE_EMAIL',
            'PHONE', 'TYPE_PHONE' => 'TYPE_PHONE',
            default => $type,
        };
    }

    private function findUserForReset(?string $type, ?string $value): ?User
    {
        if (!$type || !$value) {
            return null;
        }

        if ($type === 'TYPE_EMAIL') {
            return $this->userRepository->findOneBy([
                'email' => trim($value),
            ]);
        }

        if ($type === 'TYPE_PHONE') {
            $phone = u($value)
                ->replace(' ', '')
                ->replace('(', '')
                ->replace(')', '')
                ->replace('-', '')
                ->replace('+', '')
                ->toString();

            return $this->userRepository->findOneBy(['phone' => $phone]);
        }

        return null;
    }

    public function resetPassword(ResetPasswordInput $dto): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $dto->token]);
        if (is_null($user))
            throw new UserNotFoundException();

        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->plainPassword);
        $user->setPassword($hashedPassword);
        $user->eraseCredentials();

        $this->userRepository->add($user, true);

        $this->dispatcher->dispatch(new ResetPasswordEvent($user));

        return $user;
    }
}