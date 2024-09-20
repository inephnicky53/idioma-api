<?php

namespace App\Service\User;

use App\Dto\ResetPasswordInput;
use App\Dto\ResetRequestedInput;
use App\Entity\OTP;
use App\Entity\User;
use App\Exception\UserNotFoundException;
use App\Repository\OTPRepository;
use App\Repository\UserRepository;
use App\Service\GeoIP;
use App\Service\SmsService;
use GeoIp2\Exception\AddressNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use function Symfony\Component\String\u;

class UserManager
{
    public function __construct(
        private readonly UserRepository              $userRepository,
        private readonly OTPRepository               $OTPRepository,
        private readonly SmsService                  $smsService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RequestStack                $stack
    )
    {
    }

    public function resetRequested(ResetRequestedInput $dto): JsonResponse
    {
        $request = $this->stack->getCurrentRequest();
        $ip = in_array($request->getClientIp(), ['127.0.0.1', '192.168.65.1', '::1']) ? '41.78.192.90' : $request->getClientIp();

        try {
            $record = GeoIP::check($ip);
            $prefix = GeoIP::countryPrefix($record->countryCode);

            $phone = u($dto->phone)
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

        if (is_null($user))
            throw new \Exception('Utilisateur non trouvé');

        $otp = OTP::generate($user, 4, 2, OTP::TYPE_USER, $user->getPhone(), $user->getId());
        $this->OTPRepository->add($otp, true);

        $message = "Votre code de réinitialisation est : {$otp->getPass()}";
        $this->smsService->sendBc($user->getPhone(), $message);

        return new JsonResponse(['message' => 'Un OTP de validation vous a été envoyé']);
    }

    public function resetPassword(ResetPasswordInput $dto)
    {
        $otp = $this->OTPRepository->findOneBy(['code' => $dto->code]);
        if (is_null($otp))
            throw new \Exception('otp not valid');

        $user = $otp->getUser();
        if (is_null($user))
            throw new UserNotFoundException();

        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->plainPassword);
        $user->setPassword($hashedPassword);
        $user->eraseCredentials();

        return $user;
    }
}