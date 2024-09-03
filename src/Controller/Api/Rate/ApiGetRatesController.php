<?php

namespace App\Controller\Api\Rate;

use App\Entity\Currency;
use App\Repository\CurrencyRepository;
use App\Repository\RateRepository;
use App\Service\RateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiGetRatesController extends AbstractController
{
    public function __invoke(
        RateService $rateService
    )
    {
        return $rateService->getRates();
    }
}
