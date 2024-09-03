<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;

final class User implements JWTUserInterface
{
    // Your own logic

    public function __construct($username, array $roles, $email)
    {
        $this->username = $username;
        $this->roles = $roles;
        $this->email = $email;
    }

    public static function createFromPayload($username, array $payload): JWTUserInterface|User
    {
        return new self(
            $username,
            $payload['roles'], // Added by default
            $payload['email']  // Custom
        );
    }

    public function getRoles(): array
    {
        return [];
    }

    public function eraseCredentials(): void
    {
        // TODO: Implement eraseCredentials() method.
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }
}
