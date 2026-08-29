<?php

namespace App\Controller\Api\Otp;

use App\ApiResource\OTPVerifyResource;
use App\Entity\OTP;
use App\Entity\User;
use App\Idioma;
use App\Repository\OTPRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiOtpVerification extends AbstractController
{
    public function __invoke(
        OTPVerifyResource $data,
        OTPRepository $OTPRepository,
        UserRepository $userRepository
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $otp = $OTPRepository->findUserAndPass($user, (string) $data->code);

        if (!$otp) {
            return $this->json(
                ['status' => Idioma::STATE_ERROR, 'message' => 'Code de vérification invalide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($otp->isExpired()) {
            $OTPRepository->remove($otp, true);

            return $this->json(
                ['status' => Idioma::STATE_ERROR, 'message' => 'Code de vérification expiré.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $type = $otp->getType();
        if ($type === OTP::TYPE_USER) {
            $user->setIsPhoneVerified(true);
            $userRepository->add($user, true);
            $OTPRepository->remove($otp, true);

            return $this->json([
                'status' => Idioma::STATE_SUCCESS,
                'message' => 'Votre numéro de téléphone a été confirmé.',
            ]);
        }

        return $this->json(
            ['status' => Idioma::STATE_ERROR, 'message' => 'Type de vérification non pris en charge.'],
            Response::HTTP_BAD_REQUEST
        );
    }
}
