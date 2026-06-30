<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\SendOtpDto;
use App\Manager\OtpManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class SendOtpProcessor implements ProcessorInterface
{
    public function __construct(
        private OtpManager      $otpManager,
        private LoggerInterface $logger,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation->getName() !== 'send_otp') {
            return $data;
        }

        if (!$data instanceof SendOtpDto) {
            $this->logger->warning('Data is not SendOtpDto', ['type' => get_class($data)]);
            throw new \Exception('Invalid data type');
        }

        $this->logger->info('SendOtpProcessor.process called', [
            'identifier' => $data->identifier,
        ]);

        $this->otpManager->sendPhoneOtp($data->identifier);

        // Réponse JSON directe (évite qu'API Platform traite le tableau comme une collection)
        return new JsonResponse(['message' => 'OTP envoyé avec succès']);
    }
}
