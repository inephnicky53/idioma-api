<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\SendSMSDTO;
use App\Service\SmsService;

final readonly class SendSMSProcessor implements ProcessorInterface
{
    public function __construct(
        private SmsService $service
    )
    {
    }

    /**
     * @param SendSMSDTO $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        return $this->service->sendBc($data->to, $data->message);
    }
}