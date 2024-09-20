<?php

namespace App\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\VerifyOTPInput;
use App\Entity\OTP;
use App\Entity\User;
use App\Repository\OTPRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

class OTPVerificationProcessor implements ProcessorInterface
{

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly OTPRepository  $OTPRepository,
        private readonly Security       $security
    )
    {
    }

    /** @throws \Exception
     * @var VerifyOTPInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $otp = $this->OTPRepository->findOneBy(['user' => $user, 'pass' => $data->code, 'type' => $data->type]);

        if (is_null($otp))
            throw new \Exception('OTP invalide');

        if ($otp->isExpired())
            throw new \Exception('OTP expiré');

        if ($data->type === OTP::TYPE_USER) {
            $user->setIsPhoneVerified(true);
            $this->userRepository->add($user, true);
        }

        $this->OTPRepository->remove($otp, true);

        return new JsonResponse(['message' => "L'OTP de validation a été validé avec succès"]);
    }
}
