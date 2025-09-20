<?php

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class NewsletterSubscriptionInput
{
    #[Assert\NotNull]
    #[Assert\Type('bool')]
    #[Groups(['newsletter:subscription'])]
    public bool $isSubscribed;
}