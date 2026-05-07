<?php

namespace App\Service;

use App\Entity\Rate;
use App\Enum\Currency;
use App\Repository\RateRepository;
use Doctrine\ORM\EntityManagerInterface;

class RateService
{
    public function __construct(
        private RateRepository $rateRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Convert an amount from one currency to another
     * 
     * @param float $amount The amount to convert
     * @param Currency $from Source currency
     * @param Currency $to Target currency
     * @return float|null The converted amount or null if no rate found
     */
    public function convert(float $amount, Currency $from, Currency $to): ?float
    {
        return $this->rateRepository->convert($amount, $from, $to);
    }

    /**
     * Get the conversion rate between two currencies
     */
    public function getRate(Currency $from, Currency $to): ?float
    {
        return $this->rateRepository->getConversionRate($from, $to);
    }

    /**
     * Get all active rates
     */
    public function getAllActiveRates(): array
    {
        return $this->rateRepository->findAllActive();
    }

    /**
     * Create or update a rate
     * When creating a new rate, deactivates existing rates for the same pair
     */
    public function setRate(Currency $from, Currency $to, float $rate, bool $deactivateOthers = true): Rate
    {
        if ($deactivateOthers) {
            $this->rateRepository->deactivateRatesForPair($from, $to);
        }

        $rateEntity = new Rate();
        $rateEntity->setFromCurrency($from);
        $rateEntity->setToCurrency($to);
        $rateEntity->setRate((string) $rate);
        $rateEntity->setIsActive(true);

        $this->entityManager->persist($rateEntity);
        $this->entityManager->flush();

        return $rateEntity;
    }

    /**
     * Format an amount with currency symbol
     */
    public function formatAmount(float $amount, Currency $currency): string
    {
        $symbol = $currency->getSymbol();
        $formatted = number_format($amount, 2, ',', ' ');
        
        return match($currency) {
            Currency::USD => $symbol . $formatted,
            Currency::CDF => $formatted . ' ' . $symbol,
        };
    }

    /**
     * Convert and format an amount
     */
    public function convertAndFormat(float $amount, Currency $from, Currency $to): ?string
    {
        $converted = $this->convert($amount, $from, $to);
        
        if ($converted === null) {
            return null;
        }

        return $this->formatAmount($converted, $to);
    }

    /**
     * Get conversion info for display
     */
    public function getConversionInfo(float $amount, Currency $from, Currency $to): ?array
    {
        $rate = $this->getRate($from, $to);
        
        if ($rate === null) {
            return null;
        }

        $converted = $amount * $rate;

        return [
            'originalAmount' => $amount,
            'originalCurrency' => $from->value,
            'convertedAmount' => round($converted, 2),
            'targetCurrency' => $to->value,
            'rate' => $rate,
            'formattedOriginal' => $this->formatAmount($amount, $from),
            'formattedConverted' => $this->formatAmount($converted, $to),
        ];
    }
}
