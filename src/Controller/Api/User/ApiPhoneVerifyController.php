<?php

namespace App\Controller\Api\User;

use App\Entity\OTP;
use App\Entity\User;
use App\Repository\OTPRepository;
use App\Service\SmsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiPhoneVerifyController extends AbstractController
{
    public function __construct(
        private readonly OTPRepository $OTPRepository
    )
    {
    }

    public function __invoke(
        SmsService $smsService
    ): JsonResponse
    {
        /** @var User $data */
        $data = $this->getUser();
        $this->OTPRepository->deleteBy($data, OTP::TYPE_USER);

        $otp = OTP::generate($data, 4,2, OTP::TYPE_USER, $data->getPhone(), $data->getId());
        $this->OTPRepository->add($otp, true);

        $message = "Votre OTP de verification est : {$otp->getPass()}";

        $smsService->sendBc($data->getPhone(), $message);

        return $this->json(['status' => true, 'message' => "Un OTP de vérification est envoyé par SMS"]);
    }
}
