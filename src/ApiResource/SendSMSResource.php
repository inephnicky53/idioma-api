<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\SendSMSDTO;
use App\State\SendSMSProcessor;

#[ApiResource(
    shortName: 'sms',
    operations: [
        new Post(
            uriTemplate: "/send/sms",
            input: SendSMSDTO::class,
            processor: SendSMSProcessor::class,
        )
    ]
)]
final class SendSMSResource
{

}
