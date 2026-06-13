<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class VerifyOtpDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'email ou le téléphone est requis')]
        public ?string $identifier = null,

        #[Assert\NotBlank(message: 'L\'OTP est requis')]
        #[Assert\Length(min: 4, max: 10, minMessage: 'L\'OTP doit contenir au moins 4 caractères', maxMessage: 'L\'OTP ne peut pas dépasser 10 caractères')]
        public ?string $otp = null,
    ) {}
}
