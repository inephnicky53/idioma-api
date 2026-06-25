<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ResendEmailVerificationDto;
use App\Manager\EmailVerificationManager;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class ResendEmailVerificationProcessor implements ProcessorInterface
{
    public function __construct(
        private EmailVerificationManager $emailVerificationManager,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation->getName() !== 'resend_email_verification') {
            return $data;
        }

        if (!$data instanceof ResendEmailVerificationDto) {
            $this->logger->warning('Data is not ResendEmailVerificationDto', ['type' => get_class($data)]);
            throw new \Exception('Invalid data type');
        }

        $this->logger->info('ResendEmailVerificationProcessor.process called', [
            'email' => $data->email,
        ]);

        $user = $this->userRepository->findOneBy(['email' => $data->email]);
        if (!$user) {
            throw new NotFoundHttpException('Utilisateur non trouvé');
        }

        $this->emailVerificationManager->sendVerificationEmail($user);

        return ['message' => 'Email de vérification renvoyé avec succès'];
    }
}
