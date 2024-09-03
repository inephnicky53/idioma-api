<?php


namespace App\Controller\Api;


use App\Entity\OTP;
use App\Entity\User;
use App\Event\UserCreatedEvent;
use App\Repository\OTPRepository;
use App\Repository\UserRepository;
use App\Service\SmsService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ApiRegisterController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly UserRepository              $userRepository,
        private readonly JWTTokenManagerInterface    $jwtManager,
        private readonly ValidatorInterface          $validator,
        private readonly OTPRepository               $OTPRepository,
        private readonly EventDispatcherInterface    $dispatcher
    )
    {
    }

    public function __invoke(
        Request    $request,
        User       $data,
        SmsService $smsService
    ): JsonResponse
    {
        $ip = in_array($request->getClientIp(), ['127.0.0.1', '::1']) ? '41.78.192.90' : $request->getClientIp();
        try {
            $data->initGeoIp($ip);
        } catch (\Exception $e) {
        }

        $data->setPassword(
            $this->userPasswordHasher->hashPassword(
                $data,
                $data->getPassword()
            )
        );

        $errors = $this->validator->validate($data);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_FORBIDDEN);
        }
        $this->userRepository->add($data, true);

        $this->OTPRepository->deleteBy($data, OTP::TYPE_USER);

        /** @var OTP $otp */
        $otp = OTP::generate($data, 4, 2, OTP::TYPE_USER, $data->getPhone(), $data->getId());
        $this->OTPRepository->add($otp, true);

        $message = "Votre code de vérification est : {$otp->getPass()}";
        $smsService->sendBc($data->getPhone(), $message);

        $token = $this->jwtManager->create($data);

        $this->dispatcher->dispatch(new UserCreatedEvent($data));

        return $this->json(['token' => $token]);
    }
}
