<?php

namespace App\Controller\Api\Otp;

use App\ApiResource\OTPVerifyResource;
use App\Entity\OTP;
use App\Entity\User;
use App\Idioma;
use App\Repository\OTPRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiOtpVerification extends AbstractController
{
    public function __invoke(
        OTPVerifyResource $data,
        OTPRepository $OTPRepository,
        UserRepository $userRepository
    ): array
    {
        //dd($data);
        /** @var User $user */
        $user = $this->getUser();
        $otp = $OTPRepository->findUserAndPass($user, $data->code);
        $type = $otp?->getType();
        $message = null;
        $status = Idioma::STATE_ERROR;

        if ($type === OTP::TYPE_USER) {
            //$user = $userRepository->find($otp?->getTypeId());
            $user->setIsPhoneVerified(true);
            $userRepository->add($user, true);
            $status = Idioma::STATE_SUCCESS;
            $message = "Votre numéro de téléphone a été confirmé";
        }

        return ['status' => $status, 'message' => $message];
    }
}
