<?php

namespace App\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\VerifyOTPInput;
use App\Entity\OTP;
use App\Entity\User;
use App\Exception\UserNotFoundException;
use App\Repository\OTPRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

class OTPVerificationProcessor implements ProcessorInterface
{

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OTPRepository  $otpRepository,
        private readonly Security       $security
    )
    {
    }

    /** @throws \Exception
     * @var VerifyOTPInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $data->token]);
        if (!$user)
            throw new UserNotFoundException();

        $otp = $this->otpRepository->findOneBy(['user' => $user, 'pass' => $data->code, 'type' => $data->type != OTP::TYPE_USER ? OTP::TYPE_RESET_PASSWORD : OTP::TYPE_USER]);
        if (is_null($otp))
            throw new \Exception('OTP invalide');

        if ($otp->isExpired())
            throw new \Exception('OTP expiré');

        if ($data->type === OTP::TYPE_USER) {
            $user->setIsPhoneVerified(true);
            $this->em->flush();
        }

        $this->otpRepository->remove($otp, true);

        return new JsonResponse(['message' => "L'OTP de validation a été validé avec succès"]);
    }
}
