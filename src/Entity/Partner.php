<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Model\UploadedFileAwareInterface;
use App\Repository\PartnerRepository;
use App\State\Partner\PartnerCollectionProvider;
use App\Trait\Datable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: PartnerRepository::class)]
#[Vich\Uploadable]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['partner:read']],
            name: 'partners',
            provider: PartnerCollectionProvider::class,
            parameters: [
                'site' => new QueryParameter(description: 'Filter by site (idioma or straton)'),
            ],
        ),
    ],
)]
class Partner implements UploadedFileAwareInterface
{
    use Datable;

    public const SITE_IDIOMA = 'idioma';
    public const SITE_STRATON = 'straton';
    public const SITE_BOTH = 'both';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['partner:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Groups(['partner:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoName = null;

    #[Vich\UploadableField(mapping: 'partners', fileNameProperty: 'logoName')]
    private ?File $logoFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['partner:read'])]
    private ?string $website = null;

    #[ORM\Column]
    #[Groups(['partner:read'])]
    private int $position = 0;

    #[ORM\Column]
    #[Groups(['partner:read'])]
    private bool $isActive = true;

    /** idioma | straton | both */
    #[ORM\Column(length: 20, options: ['default' => 'both'])]
    #[Groups(['partner:read'])]
    private string $site = self::SITE_BOTH;

    #[Groups(['partner:read'])]
    public function getLogoUrl(): ?string
    {
        if (!$this->logoName) {
            return null;
        }

        return '/uploads/partners/'.$this->logoName;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLogoName(): ?string
    {
        return $this->logoName;
    }

    public function setLogoName(?string $logoName): static
    {
        $this->logoName = $logoName;

        return $this;
    }

    public function getLogoFile(): ?File
    {
        return $this->logoFile;
    }

    public function setLogoFile(?File $logoFile): static
    {
        $this->logoFile = $logoFile;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

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

    public function getFilePropertyMapping(): array
    {
        return ['logoName' => 'logoFile'];
    }

    public function __toString(): string
    {
        return $this->name ?? 'Partenaire';
    }
}
