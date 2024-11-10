<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordInput
{
    #[Assert\NotNull]
    #[Assert\NotBlank]
    public ?string $plainPassword = null;
}