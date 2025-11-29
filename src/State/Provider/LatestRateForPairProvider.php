<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Enum\Currency;
use App\Repository\RateRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provider pour récupérer le dernier taux actif pour une paire de devises spécifique
 */
class LatestRateForPairProvider implements ProviderInterface
{
    public function __construct(
        private RateRepository $rateRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $from = $uriVariables['from'] ?? null;
        $to = $uriVariables['to'] ?? null;

        if (!$from || !$to) {
            throw new BadRequestHttpException('Les paramètres from et to sont requis');
        }

        $fromCurrency = Currency::tryFrom(strtoupper($from));
        $toCurrency = Currency::tryFrom(strtoupper($to));

        if (!$fromCurrency || !$toCurrency) {
            throw new BadRequestHttpException('Code devise invalide. Supportés: USD, CDF');
        }

        $rate = $this->rateRepository->findLatestRate($fromCurrency, $toCurrency);

        if (!$rate) {
            throw new NotFoundHttpException('Aucun taux trouvé pour cette paire de devises');
        }

        return $rate;
    }
}

