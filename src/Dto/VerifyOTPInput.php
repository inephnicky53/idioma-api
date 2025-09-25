<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class VerifyOTPInput
{
    #[Assert\NotNull]
    #[Assert\NotBlank]
    public int $code;

    #[Assert\NotNull]
    #[Assert\NotBlank]
    public string $type;

    #[Assert\NotNull]
    #[Assert\NotBlank]
    public string $token;
}