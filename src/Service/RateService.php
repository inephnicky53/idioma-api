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
