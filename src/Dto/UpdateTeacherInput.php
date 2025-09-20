<?php

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class UpdateTeacherInput
{
    #[Groups(['teacher:update'])]
    public ?string $shortDescription = null;

    #[Groups(['teacher:update'])]
    public ?string $description = null;

    #[Groups(['teacher:update'])]
    public ?string $experience = null;

    #[Groups(['teacher:update'])]
    public ?string $motivation = null;

    #[Groups(['teacher:update'])]
    public ?string $timezone = null;

    #[Groups(['teacher:update'])]
    public ?string $profile = null;

    #[Groups(['teacher:update'])]
    public ?float $price = null;

    #[Groups(['teacher:update'])]
    public array $spokenLanguages = [];
}