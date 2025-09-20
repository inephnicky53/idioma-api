<?php

namespace App\Model;

class FormationModel
{
    public function __construct(
        public ?string $university = null,
        public ?string $speciality = null,
        public ?string $certificate = null,
        public ?string $yearStart = null,
        public ?string $yearEnd = null,
        public ?string $file = null,
        public ?string $proofImage = null
    )
    {
    }
}