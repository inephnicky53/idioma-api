<?php

namespace App\Repository;

use App\Entity\Rate;
use App\Enum\Currency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rate>
 */
class RateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rate::class);
    }

    /**
     * Find the active rate for a currency pair
     */
    public function findActiveRate(Currency $from, Currency $to): ?Rate
    {
        return $this->createQueryBuilder('r')
            ->where('r.fromCurrency = :from')
            ->andWhere('r.toCurrency = :to')
            ->andWhere('r.isActive = :active')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('active', true)
            ->orderBy('r.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all active rates
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('r.fromCurrency', 'ASC')
            ->addOrderBy('r.toCurrency', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the latest active rate for each currency pair
     * Returns only one rate per pair (the most recent)
     */
    public function findLatestActiveRates(): array
    {
        // Subquery to get the max id for each currency pair
        $subQuery = $this->createQueryBuilder('r2')
            ->select('MAX(r2.id)')
            ->where('r2.isActive = :active')
            ->groupBy('r2.fromCurrency, r2.toCurrency')
            ->getDQL();

        return $this->createQueryBuilder('r')
            ->where('r.isActive = :active')
            ->andWhere('r.id IN (' . $subQuery . ')')
            ->setParameter('active', true)
            ->orderBy('r.fromCurrency', 'ASC')
            ->addOrderBy('r.toCurrency', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the latest active rate for a specific currency pair
     */
    public function findLatestRate(Currency $from, Currency $to): ?Rate
    {
        return $this->createQueryBuilder('r')
            ->where('r.fromCurrency = :from')
            ->andWhere('r.toCurrency = :to')
            ->andWhere('r.isActive = :active')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('active', true)
            ->orderBy('r.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get the conversion rate between two currencies
     * Returns null if no rate is found
     */
    public function getConversionRate(Currency $from, Currency $to): ?float
    {
        // Same currency, rate is 1
        if ($from === $to) {
            return 1.0;
        }

        // Try direct rate
        $rate = $this->findActiveRate($from, $to);
        if ($rate) {
            return (float) $rate->getRate();
        }

        // Try reverse rate
        $reverseRate = $this->findActiveRate($to, $from);
        if ($reverseRate && (float) $reverseRate->getRate() !== 0.0) {
            return 1.0 / (float) $reverseRate->getRate();
        }

        return null;
    }

    /**
     * Convert an amount from one currency to another
     */
    public function convert(float $amount, Currency $from, Currency $to): ?float
    {
        $rate = $this->getConversionRate($from, $to);

        if ($rate === null) {
            return null;
        }

        return $amount * $rate;
    }

    /**
     * Deactivate all rates for a currency pair
     */
    public function deactivateRatesForPair(Currency $from, Currency $to): void
    {
        $this->createQueryBuilder('r')
            ->update()
            ->set('r.isActive', ':inactive')
            ->where('r.fromCurrency = :from')
            ->andWhere('r.toCurrency = :to')
            ->setParameter('inactive', false)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->execute();
    }
}
