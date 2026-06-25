<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ForgotPasswordDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'email est requis')]
        #[Assert\Email(message: 'L\'email n\'est pas valide')]
        public ?string $email = null,
    ) {}
}
