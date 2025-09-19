<?php

namespace App\Model;

use App\Entity\Language;

class CertificationModel
{
    public ?string $certification = null;

    public ?Language $language = null;

    public ?\DateTimeImmutable $yearStart = null;

    public ?\DateTimeImmutable $yearEnd = null;

    public ?string $file = null;
}