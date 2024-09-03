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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class       ApiRegisterController extends AbstractController
{
    public function __construct(
        private UserPasswordHasherInterface $userPasswordHasher,
        private UserRepository $userRepository,
        private JWTTokenManagerInterface $jwtManager,
        private ValidatorInterface $validator,
        private OTPRepository $OTPRepository,
        private EventDispatcherInterface $dispatcher
    ){}

    public function __invoke(
        Request $request,
        User $data,
        SmsService $smsService
    )
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

        /*if (in_array('ROLE_STUDENT', $data->getRoles())){
            $student = new Student();
            //$student->s
        }*/

        //dd($data);
        $errors = $this->validator->validate($data);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_FORBIDDEN);
        }
        $this->userRepository->add($data, true);

        $this->OTPRepository->deleteBy($data, OTP::TYPE_USER);

        $otp = OTP::generate($data, 4,2, OTP::TYPE_USER, $data->getPhone(), $data->getId());
        $this->OTPRepository->add($otp, true);

        $message = "Votre code de verification est : {$otp->getPass()}";
        $smsService->sendBc($data->getPhone(), $message);

        $token = $this->jwtManager->create($data);

        $this->dispatcher->dispatch(new UserCreatedEvent($data));

        return $this->json(['token' => $token]);
    }
}
