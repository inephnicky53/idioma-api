<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\ConversionResultDto;
use App\Enum\Currency;
use App\Service\RateService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provider pour convertir un montant d'une devise à une autre
 */
class ConvertCurrencyProvider implements ProviderInterface
{
    public function __construct(
        private readonly RateService $rateService,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();

        $amount = (float) $request->query->get('amount', 0);
        $from = $request->query->get('from', '');
        $to = $request->query->get('to', '');

        if ($amount <= 0) {
            throw new BadRequestHttpException('Le montant doit être positif');
        }

        $fromCurrency = Currency::tryFrom(strtoupper($from));
        $toCurrency = Currency::tryFrom(strtoupper($to));

        if (!$fromCurrency || !$toCurrency) {
            throw new BadRequestHttpException('Code devise invalide. Supportés: USD, CDF');
        }

        $result = $this->rateService->getConversionInfo($amount, $fromCurrency, $toCurrency);

        if (!$result) {
            throw new NotFoundHttpException('Aucun taux trouvé pour cette paire de devises');
        }

        return new ConversionResultDto(
            originalAmount: $result['original'],
            originalCurrency: $result['from'],
            convertedAmount: $result['converted'],
            targetCurrency: $result['to'],
            rate: $result['rate'],
            formattedOriginal: $result['formattedOriginal'],
            formattedConverted: $result['formattedConverted']
        );
    }
}

