<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ResetPasswordDto;
use App\Manager\PasswordResetManager;
use Psr\Log\LoggerInterface;

readonly class ResetPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private PasswordResetManager $passwordResetManager,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation->getName() !== 'reset_password') {
            return $data;
        }

        if (!$data instanceof ResetPasswordDto) {
            $this->logger->warning('Data is not ResetPasswordDto', ['type' => get_class($data)]);
            throw new \Exception('Invalid data type');
        }

        $this->logger->info('ResetPasswordProcessor.process called');

        $this->passwordResetManager->resetPassword($data->token, $data->password);

        return ['message' => 'Mot de passe réinitialisé avec succès'];
    }
}
