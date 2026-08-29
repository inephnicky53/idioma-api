<?php

namespace App\Dto;

use App\Model\AvailabilityModel;
use App\Model\CertificationModel;
use App\Model\FormationModel;
use App\Model\LanguageModel;
use Symfony\Component\Validator\Constraints as Assert;

class CreateTeacherInput
{
    #[Assert\NotNull]
    #[Assert\NotBlank]
    public ?string $fullname = null;

    public ?string $firstname = null; 

    public ?string $lastname = null; 

    #[Assert\NotBlank]
    public ?string $country = null;

    #[Assert\NotBlank]
    public ?string $language = null;

    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^\+?\d{10,15}$/',
        message: 'Le numéro de téléphone doit commencer éventuellement par + et contenir entre 10 et 15 chiffres.'
    )]
    public ?string $phone = null;

    #[Assert\NotNull]
    #[Assert\Positive] // S'assurer que le prix est positif
    public ?float $price = null;

    #[Assert\NotBlank]
    public ?string $currency = null;

    public ?string $profile = null; 

    public ?string $video = null; 

    public ?string $videoPoster = null;

    public ?string $link = null; 

    public ?string $shortDescription = null; 

    public ?string $description = null; 

    public ?string $experience = null; 

    public ?string $motivation = null; 

    public ?string $hookTitle = null; 

    public ?string $timezone = null; 

    /** @var CertificationModel[] */
    public array $certifications = [];

    /** @var FormationModel[] */
    public array $formations = [];

    /** @var AvailabilityModel[] */
    public array $availabilities = [];
    /** @var LanguageModel[] */
    public array $spokenLanguages = [];
    /** @var LanguageModel[] */
    public array $languages = [];

    public function __construct()
    {
        $this->certifications = [];
        $this->formations = [];
        $this->availabilities = [];
        $this->spokenLanguages = [];
        $this->languages = [];
    }
}