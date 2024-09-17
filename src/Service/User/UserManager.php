<?php

namespace App\Service\User;

use App\Dto\ResetPasswordInput;
use App\Dto\ResetRequestedInput;
use App\Entity\OTP;
use App\Entity\User;
use App\Exception\UserNotFoundException;
use App\Repository\OTPRepository;
use App\Repository\UserRepository;
use App\Service\SmsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManager
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly OTPRepository  $OTPRepository,
        private readonly SmsService     $smsService,
        private readonly UserPasswordHasherInterface $passwordHasher,
    )
    {
    }

    public function resetRequested(ResetRequestedInput $dto)
    {
        $user = $this->userRepository->findOneBy(['phone' => $dto->phone]);

        if (is_null($user))
            throw new UserNotFoundException();

        /** @var OTP $otp */
        $otp = OTP::generate($user, 4, 2, OTP::TYPE_USER, $user->getPhone(), $user->getId());
        $this->OTPRepository->add($otp, true);

        $message = "Votre code de réinitialisation est : {$otp->getPass()}";
        $this->smsService->sendBc($user->getPhone(), $message);

        return new JsonResponse([]);
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