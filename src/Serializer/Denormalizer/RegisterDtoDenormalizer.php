<?php

namespace App\Serializer\Denormalizer;

use App\Dto\RegisterDto;
use App\Enum\Currency;
use Symfony\Component\Serializer\Denormalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

class RegisterDtoDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Expected array');
        }

        // Créer une instance vide
        $dto = new RegisterDto();

        // Convertir la devise en enum si fournie
        if (isset($data['currency']) && is_string($data['currency'])) {
            try {
                $dto->currency = Currency::from($data['currency']);
            } catch (\ValueError $e) {
                throw new InvalidArgumentException("Invalid currency: {$data['currency']}");
            }
        }

        // Assigner les propriétés
        $dto->email = $data['email'] ?? '';
        $dto->password = $data['password'] ?? '';
        $dto->firstName = $data['firstName'] ?? '';
        $dto->lastName = $data['lastName'] ?? '';
        $dto->phone = $data['phone'] ?? null;
        $dto->phonePayment = $data['phonePayment'] ?? null;
        $dto->level = $data['level'] ?? null;
        $dto->participationType = $data['participationType'] ?? null;
        $dto->subscriptionPlanId = isset($data['subscriptionPlanId']) ? (int)$data['subscriptionPlanId'] : null;
        $dto->paymentMethod = $data['paymentMethod'] ?? null;

        return $dto;
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $type === RegisterDto::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            RegisterDto::class => true,
        ];
    }
}

