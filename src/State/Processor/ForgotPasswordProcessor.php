<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ForgotPasswordDto;
use App\Manager\PasswordResetManager;
use Psr\Log\LoggerInterface;

readonly class ForgotPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private PasswordResetManager $passwordResetManager,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation->getName() !== 'forgot_password') {
            return $data;
        }

        if (!$data instanceof ForgotPasswordDto) {
            $this->logger->warning('Data is not ForgotPasswordDto', ['type' => get_class($data)]);
            throw new \Exception('Invalid data type');
        }

        $this->logger->info('ForgotPasswordProcessor.process called', [
            'email' => $data->email,
        ]);

        $this->passwordResetManager->sendResetPasswordEmail($data->email);

        return ['message' => 'Un email de réinitialisation de mot de passe a été envoyé'];
    }
}
