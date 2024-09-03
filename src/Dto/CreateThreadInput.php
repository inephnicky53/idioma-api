<?php

namespace App\Dto;

use App\Model\AvailabilityModel;
use App\Model\CertificationModel;
use App\Model\FormationModel;
use App\Model\LanguageModel;
use Symfony\Component\Validator\Constraints as Assert;

class CreateThreadInput
{
    #[Assert\NotNull]
    #[Assert\NotBlank]
    public string $teacher;

    public array $participants;

    public ?string $course;

    #[Assert\NotNull]
    #[Assert\NotBlank]
    public string $body;

}