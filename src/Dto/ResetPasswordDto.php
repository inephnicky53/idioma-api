<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le token est requis')]
        public ?string $token = null,

        #[Assert\NotBlank(message: 'Le mot de passe est requis')]
        #[Assert\Length(min: 6, minMessage: 'Le mot de passe doit contenir au moins 6 caractères')]
        public ?string $password = null,

        #[Assert\NotBlank(message: 'La confirmation du mot de passe est requis')]
        #[Assert\EqualTo(propertyPath: 'password', message: 'Les mots de passe ne correspondent pas')]
        public ?string $passwordConfirm = null,
    ) {}
}
