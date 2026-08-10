<?php

namespace App\Service;

class FeatureFlags
{
    public function __construct(private readonly array $features)
    {
    }

    public function isEnabled(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }

    public function all(): array
    {
        return $this->features;
    }
}
