<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ResetRequestedInput
{
    #[Assert\NotNull]
    #[Assert\NotBlank]
    public ?string $type = null;
    

    #[Assert\NotNull]
    #[Assert\NotBlank]
    public ?string $value = null;
}