<?php

namespace App\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserPasswordHasher implements ProcessorInterface
{
    public function __construct(
        private readonly RequestStack                $requestStack,
        private readonly ProcessorInterface          $processor,
        private readonly UserPasswordHasherInterface $passwordHasher,
    )
    {
    }

    /**
     * @throws \Exception
     * @var User $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        if (is_null($data->getIp())) {
            $request = $this->requestStack->getCurrentRequest();
            $ip = in_array($request->getClientIp(), ['127.0.0.1', '::1']) ? '41.78.192.90' : $request->getClientIp();
            $data->initGeoIp($ip);
        }

        if ($data->getPlainPassword()) {
            $hashedPassword = $this->passwordHasher->hashPassword($data, $data->getPlainPassword());
            $data->setPassword($hashedPassword);
            $data->eraseCredentials();
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}