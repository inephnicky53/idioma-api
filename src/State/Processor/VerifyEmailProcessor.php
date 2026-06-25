<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\VerifyEmailDto;
use App\Manager\EmailVerificationManager;
use Psr\Log\LoggerInterface;

readonly class VerifyEmailProcessor implements ProcessorInterface
{
    public function __construct(
        private EmailVerificationManager $emailVerificationManager,
        private LoggerInterface $logger,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation->getName() !== 'verify_email') {
            return $data;
        }

        if (!$data instanceof VerifyEmailDto) {
            $this->logger->warning('Data is not VerifyEmailDto', ['type' => get_class($data)]);
            throw new \Exception('Invalid data type');
        }

        $this->logger->info('VerifyEmailProcessor.process called');

        $user = $this->emailVerificationManager->verifyEmail($data->token);

        return [
            'message' => 'Email vérifié avec succès',
            'verified' => true,
            'user' => $user,
        ];
    }
}
