<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Repository\SiteContactRepository;
use App\State\Site\SiteContactCollectionProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SiteContactRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/site-contacts',
            normalizationContext: ['groups' => ['site_contact:read']],
            provider: SiteContactCollectionProvider::class,
            paginationEnabled: false,
            parameters: [
                'site' => new QueryParameter(description: 'Filter by site (idioma or straton)'),
            ],
        ),
    ],
)]
class SiteContact
{
    public const SITE_IDIOMA = 'idioma';
    public const SITE_STRATON = 'straton';
    public const SITE_BOTH = 'both';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['site_contact:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Groups(['site_contact:read'])]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['site_contact:read'])]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['site_contact:read'])]
    private ?string $address = null;

    /** idioma | straton | both */
    #[ORM\Column(length: 20, options: ['default' => 'both'])]
    #[Groups(['site_contact:read'])]
    private string $site = self::SITE_BOTH;

    #[ORM\Column]
    #[Groups(['site_contact:read'])]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getSite(): string
    {
        return $this->site;
    }

    public function setSite(string $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function __toString(): string
    {
        return $this->email ?: $this->phone ?: $this->address ?: 'Coordonnées';
    }
}
