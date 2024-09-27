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
use App\Service\GeoIP;
use App\Service\SmsService;
use GeoIp2\Exception\AddressNotFoundException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
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
        private readonly RequestStack                $stack,
        private readonly MailerInterface             $mailer,
        private readonly EventDispatcherInterface    $dispatcher
    )
    {
    }

    public function resetRequested(ResetRequestedInput $dto): JsonResponse
    {
        $user = null;

        if ($dto->type === "TYPE_PHONE") {
            $request = $this->stack->getCurrentRequest();
            $ip = in_array($request->getClientIp(), ['127.0.0.1', '192.168.65.1', '::1']) ? '41.78.192.90' : $request->getClientIp();

            try {
                $record = GeoIP::check($ip);
                $prefix = GeoIP::countryPrefix($record->countryCode);

                $phone = u($dto->value)
                    ->replace(' ', '')
                    ->replace('(', '')
                    ->replace(')', '');

                if ($phone->startsWith($prefix))
                    $phoneNumber = $phone;
                elseif ($phone->startsWith("+$prefix"))
                    $phoneNumber = $phone->splice('', 0, 1);
                elseif ($phone->startsWith("00$prefix"))
                    $phoneNumber = $phone->splice('', 0, 2);
                elseif ($phone->startsWith("0"))
                    $phoneNumber = $phone->splice($prefix, 0, 1);
                elseif ($phone->startsWith("+"))
                    $phoneNumber = $phone->splice('', 0, 1);
                else
                    $phoneNumber = $phone->prepend($prefix);
            } catch (AddressNotFoundException $ex) {
                throw new \Exception("It wasn't possible to retrieve information about the providen IP");
            }

            $user = $this->userRepository->findOneBy(['phone' => $phoneNumber]);
        } 
        if ($dto->type === "TYPE_EMAIL") {
            $user = $this->userRepository->findOneBy(['email' => $dto->value]);
        }

        if (is_null($user))
            throw new \Exception('Utilisateur non trouvé');

        $this->OTPRepository->deleteBy($user, OTP::TYPE_RESET_PASSWORD);

        $otp = OTP::generate($user, 4, 2, OTP::TYPE_RESET_PASSWORD, $user->getPhone(), $user->getId());
        $this->OTPRepository->add($otp, true);

        if ($dto->type === "TYPE_PHONE") {
            $message = "Votre code de réinitialisation est : {$otp->getPass()}";
            $this->smsService->sendBc($user->getPhone(), $message);
        }
        if ($dto->type === "TYPE_EMAIL") {
            try {
                $subject = "Demande de réinitialisation de mot de passe";
                $email = (new TemplatedEmail())
                    ->to(new Address($user->getEmail()))
                    ->subject($subject)
                    ->htmlTemplate('user/email/reinitialisation.mjml.twig')
                    ->context([
                        'user' => $user,
                        'subject' => $subject,
                        'otp' => $otp
                    ]);

                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
            }
        }

        $token = $this->jwtManager->create($user);

        return new JsonResponse(['message' => 'Un OTP de validation vous a été envoyé', 'token' => $token]);
    }

    public function resetPassword(ResetPasswordInput $dto): User
    {
        /** @var User $user */
        $user = $this->security->getUser();
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