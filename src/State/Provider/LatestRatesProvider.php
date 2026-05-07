<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\RateRepository;

/**
 * Provider pour récupérer les derniers taux actifs (un par paire de devises)
 */
class LatestRatesProvider implements ProviderInterface
{
    public function __construct(
        private RateRepository $rateRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->rateRepository->findLatestActiveRates();
    }
}

