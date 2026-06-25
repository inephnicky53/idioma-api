<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\VerifyOtpDto;
use App\Manager\OtpManager;
use Psr\Log\LoggerInterface;

readonly class VerifyOtpProcessor implements ProcessorInterface
{
    public function __construct(
        private OtpManager      $otpManager,
        private LoggerInterface $logger,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation->getName() !== 'verify_otp') {
            return $data;
        }

        if (!$data instanceof VerifyOtpDto) {
            $this->logger->warning('Data is not VerifyOtpDto', ['type' => get_class($data)]);
            throw new \Exception('Invalid data type');
        }

        $this->logger->info('VerifyOtpProcessor.process called', [
            'identifier' => $data->identifier,
        ]);

        $user = $this->otpManager->verifyPhoneOtp($data->identifier, $data->otp);

        return [
            'message' => 'OTP vérifié avec succès',
            'verified' => true,
            'user' => $user,
        ];
    }
}
