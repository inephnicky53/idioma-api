<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\Otp\ApiOtpVerification;
use App\State\CrmProvider;

#[ApiResource(
    shortName: 'OTP',
    operations: [
        new Post(
            uriTemplate: "/otp/verify",
            controller: ApiOtpVerification::class,
            openapiContext: [
                'summary' => self::DESCRIPTION
            ],
            normalizationContext: ['otp:list'],
            security: 'is_granted("ROLE_USER")',
            write: false
        )
    ]
)]
final class OTPVerifyResource
{
    public const DESCRIPTION = "Verifie le OTP de l'utilisateur.";

    public int $code;
}
