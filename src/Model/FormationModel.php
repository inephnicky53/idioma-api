<?php

namespace App\Model;

class FormationModel
{
    public function __construct(
        public string $university,
        public string $speciality,
        public string $certificate,
        public string $type,
        public string $yearStart,
        public string $yearEnd,
        public bool   $hasCertificate
    )
    {
    }
}