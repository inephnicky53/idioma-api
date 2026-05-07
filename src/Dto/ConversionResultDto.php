<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\State\Provider\ConvertCurrencyProvider;

class ConversionResultDto
{
    public function __construct(
        public readonly float $originalAmount,
        public readonly string $originalCurrency,
        public readonly float $convertedAmount,
        public readonly string $targetCurrency,
        public readonly float $rate,
        public readonly string $formattedOriginal,
        public readonly string $formattedConverted
    ) {
    }
}

