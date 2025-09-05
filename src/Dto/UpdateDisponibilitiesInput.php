<?php

namespace App\Dto;

use App\Model\AvailabilityModel;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateDisponibilitiesInput
{
    /**
     * @var AvailabilityModel[]
     */
    #[Assert\NotNull]
    #[Assert\Valid]
    public array $availabilities = [];
}