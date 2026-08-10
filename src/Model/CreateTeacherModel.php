<?php

namespace App\Model;

class CreateTeacherModel
{
    public function __construct(
        public string $fullname,

        public ?string $firstname,

        public ?string $lastname,

        public string $country,

        public string $language,

        public string $phone,

        public float  $price,

        public string $currency,

        public ?string $profile,

        public ?string $video,

        public ?string $link,

        public ?string $shortDescription,

        public ?string $description,

        public ?string $experience,

        public ?string $motivation,

        public ?string $hookTitle,

        public ?string $timezone,

        /** @var CertificationModel[] $certifications */
        public array  $certifications = [],

        /** @var FormationModel[] $formations */
        public array  $formations = [],

        /** @var AvailabilityModel[] $presentations */
        public array  $availabilities = [],

        /** @var LanguageModel[] $spokenLanguages */
        public array  $spokenLanguages = [],

        /** @var LanguageModel[] $languages */
        public array  $languages = [],
    )
    {
    }
}