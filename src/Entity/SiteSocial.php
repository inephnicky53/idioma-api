<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Repository\SiteSocialRepository;
use App\State\Site\SiteSocialCollectionProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SiteSocialRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/site-socials',
            normalizationContext: ['groups' => ['site_social:read']],
            provider: SiteSocialCollectionProvider::class,
            paginationEnabled: false,
            parameters: [
                'site' => new QueryParameter(description: 'Filter by site (idioma or straton)'),
            ],
        ),
    ],
)]
class SiteSocial
{
    public const SITE_IDIOMA = 'idioma';
    public const SITE_STRATON = 'straton';
    public const SITE_BOTH = 'both';

    public const ICONS = [
        'facebook' => 'icon-facebook',
        'twitter' => 'icon-twitter',
        'instagram' => 'icon-instagram',
        'linkedin' => 'icon-linkedin',
        'youtube' => 'icon-play',
        'tiktok' => 'icon-video-file',
        'website' => 'icon-worldwide',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['site_social:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    #[Groups(['site_social:read'])]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    #[Groups(['site_social:read'])]
    private ?string $link = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['site_social:read'])]
    private ?string $icon = null;

    #[ORM\Column]
    #[Groups(['site_social:read'])]
    private int $position = 0;

    #[ORM\Column]
    #[Groups(['site_social:read'])]
    private bool $isActive = true;

    /** idioma | straton | both */
    #[ORM\Column(length: 20, options: ['default' => 'both'])]
    #[Groups(['site_social:read'])]
    private string $site = self::SITE_BOTH;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        $this->icon = self::ICONS[$type] ?? 'icon-worldwide';

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(string $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

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

    public function getSite(): string
    {
        return $this->site;
    }

    public function setSite(string $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function __toString(): string
    {
        return ($this->type ?? 'réseau').' '.($this->link ?? '');
    }
}
