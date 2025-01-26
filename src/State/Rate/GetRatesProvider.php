<?php

namespace App\State\Rate;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Service\RateService;

readonly class GetRatesProvider implements ProviderInterface
{
    public function __construct(private RateService $rateService)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->rateService->getRates();
    }
}
