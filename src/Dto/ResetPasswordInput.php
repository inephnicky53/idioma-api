<?php

namespace App\Dto;

use App\Model\AvailabilityModel;
use App\Model\CertificationModel;
use App\Model\FormationModel;
use App\Model\LanguageModel;
use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordInput
{
    #[Assert\NotNull]
    #[Assert\NotBlank]
    public ?string $plainPassword = null;
}