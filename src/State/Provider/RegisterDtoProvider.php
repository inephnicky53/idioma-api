<?php

namespace App\State\Provider;

use App\Dto\RegisterDto;
use App\Enum\Currency;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;

class RegisterDtoProvider implements ProviderInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $this->logger->info('RegisterDtoProvider.provide called');

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            $this->logger->warning('No request found');
            return null;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $this->logger->warning('Invalid JSON data');
            return null;
        }

        $this->logger->info('RegisterDtoProvider creating DTO', ['data' => $data]);

        // Convertir la devise en enum si fournie
        $currency = null;
        if (isset($data['currency']) && is_string($data['currency'])) {
            try {
                $currency = Currency::from($data['currency']);
            } catch (\ValueError $e) {
                // Ignorer les devises invalides
            }
        }

        // Créer le DTO avec les données
        return new RegisterDto(
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
            firstName: $data['firstName'] ?? '',
            lastName: $data['lastName'] ?? '',
            phone: $data['phone'] ?? null,
            phonePayment: $data['phonePayment'] ?? null,
            level: $data['level'] ?? null,
            participationType: $data['participationType'] ?? null,
            subscriptionPlanId: isset($data['subscriptionPlanId']) ? (int)$data['subscriptionPlanId'] : null,
            paymentMethod: $data['paymentMethod'] ?? null,
            currency: $currency,
        );
    }
}

