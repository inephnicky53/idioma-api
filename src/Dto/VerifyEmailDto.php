<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class VerifyEmailDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le token est requis')]
        public ?string $token = null,
    ) {}
}
