<?php

namespace App\Service;

use App\Entity\Currency;
use App\Entity\Rate;
use App\Repository\CurrencyRepository;
use App\Repository\RateRepository;

class RateService
{
    private array $rates = [];

    public function __construct(
        private readonly RateRepository     $rateRepository,
        private readonly CurrencyRepository $currencyRepository
    )
    {
        $this->check();
    }

    private function check(): void
    {
        $rates = $this->rateRepository->findBy([], ['id' => "DESC"]);

        array_map(function (Currency $currency) use (&$new_rates, $rates) {
            foreach ($rates as $rate) {
                if ($currency === $rate->getCurrency()) {
                    $new_rates[] = $rate;
                    break;
                }
            }
        }, $this->currencyRepository->findAll());

        $this->rates = $rates;
    }

    /**
     * Give current rates for all currencies
     * @return array
     */
    public function getRates(): array
    {
        return $this->rates;
    }

    public function find(?Currency $currency): ?Rate
    {
        $finded = array_filter($this->getRates(), fn(Rate $r) => $r->getCurrency() === $currency);
        return array_shift($finded);
    }

    /**
     * Latest rate for a currency code, matched by value rather than by object
     * identity so it also works with detached/duplicated Currency instances.
     * `$this->rates` is ordered id DESC, so the first hit is the most recent.
     */
    public function findByCode(?string $code): ?Rate
    {
        if (!$code)
            return null;

        foreach ($this->getRates() as $rate) {
            if ($rate->getCurrency()?->getMin() === $code)
                return $rate;
        }

        return null;
    }

    /**
     * Convert an amount between two currencies using the admin-defined rates.
     *
     * Convention: there is one Rate row per currency, and Rate::value is that
     * currency's price expressed against a single pivot shared by every row
     * (the row whose value is 1 — USD today). Rate::reference is a free-text
     * label naming where the rate comes from ("Equity", "BCC"…), NOT a currency
     * code, so it plays no part in the maths.
     *
     * Going through the pivot makes the conversion symmetric and independent of
     * which currency the pivot actually is:
     *   amount / value(from) → pivot → × value(to)
     * With USD=1, EUR=0.8, CDF=2230: 19 EUR = 19/0.8 × 2230 = 52 962 CDF.
     *
     * Throws instead of returning the amount untouched: silently skipping the
     * conversion at checkout would charge a EUR price as the same number of CDF.
     */
    public function convert(float $amount, ?Currency $from, ?Currency $to): float
    {
        $fromCode = $from?->getMin();
        $toCode = $to?->getMin();

        if (!$fromCode || !$toCode || $fromCode === $toCode)
            return $amount;

        $fromRate = $this->findByCode($fromCode);
        $toRate = $this->findByCode($toCode);

        if (!$fromRate || !$toRate)
            throw new \RuntimeException("Aucun taux de change défini pour $fromCode → $toCode.");

        $fromValue = $fromRate->getValue();
        $toValue = $toRate->getValue();

        if (!$fromValue || !$toValue || $fromValue <= 0 || $toValue <= 0)
            throw new \RuntimeException("Taux de change invalide pour $fromCode → $toCode.");

        return $amount / $fromValue * $toValue;
    }

    public function resolveAmount(float $amount, Currency $currency, ?Rate $rate = null)
    {
        if ($rate === null)
            $rate = $this->find($currency);

        if ($currency === $rate->getCurrency())
            return  $amount;
        elseif ($currency->getMin() != $rate->getCurrency()->getMin()) {
            //dd(22);
            $amount = $amount * $rate->getValue();
        } else {
            //dd(23);
            $amount = $amount / $rate->getValue();
        }

        return $amount;
    }
}
