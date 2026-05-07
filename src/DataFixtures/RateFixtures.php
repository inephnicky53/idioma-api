<?php

namespace App\DataFixtures;

use App\Entity\Rate;
use App\Enum\Currency;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RateFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Taux USD vers CDF (1 USD = ~2800 CDF - taux approximatif)
        $usdToCdf = new Rate();
        $usdToCdf->setFromCurrency(Currency::USD);
        $usdToCdf->setToCurrency(Currency::CDF);
        $usdToCdf->setRate('2800.000000');
        $usdToCdf->setIsActive(true);
        $manager->persist($usdToCdf);

        // Taux CDF vers USD (1 CDF = ~0.000357 USD)
        $cdfToUsd = new Rate();
        $cdfToUsd->setFromCurrency(Currency::CDF);
        $cdfToUsd->setToCurrency(Currency::USD);
        $cdfToUsd->setRate('0.000357');
        $cdfToUsd->setIsActive(true);
        $manager->persist($cdfToUsd);

        $manager->flush();
    }
}
