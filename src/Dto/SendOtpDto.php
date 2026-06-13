<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class SendOtpDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'email ou le téléphone est requis')]
        public ?string $identifier = null,
    ) {}
}
