<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Dto\ConversionResultDto;
use App\Enum\Currency;
use App\Repository\RateRepository;
use App\State\Provider\ConvertCurrencyProvider;
use App\State\Provider\LatestRatesProvider;
use App\State\Provider\LatestRateForPairProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RateRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_currency_pair', columns: ['from_currency', 'to_currency', 'is_active'])]
#[ApiResource(
    operations: [
        // Opération pour récupérer les derniers taux actifs (un par paire)
        new GetCollection(
            uriTemplate: '/rates/latest',
            description: 'Récupère les derniers taux actifs (un par paire de devises)',
            name: 'get_latest_rates',
            provider: LatestRatesProvider::class
        ),
        new Get(),
        new Get(
            uriTemplate: '/rates/latest/{from}/{to}',
            uriVariables: ['from', 'to'],
            description: 'Récupère le dernier taux actif pour une paire de devises',
            name: 'get_latest_rate_for_pair',
            provider: LatestRateForPairProvider::class
        ),
        // Opérations CRUD standard
        new GetCollection(
            description: 'Récupère tous les taux de change',
            name: 'get_rates'
        ),
        new Get(
            description: 'Récupère un taux de change par ID',
            name: 'get_rate'
        ),
        new Get(
            uriTemplate: '/rates/convert',
            description: 'Convertir un montant d\'une devise à une autre',
            output: ConversionResultDto::class,
            name: 'convert_currency',
            provider: ConvertCurrencyProvider::class
        ),
        new Post(
            description: 'Crée un nouveau taux de change (admin uniquement)',
            security: 'is_granted("ROLE_ADMIN")'
        ),
        new Patch(
            description: 'Met à jour un taux de change (admin uniquement)',
            security: 'is_granted("ROLE_ADMIN")'
        ),
        new Delete(
            description: 'Supprime un taux de change (admin uniquement)',
            security: 'is_granted("ROLE_ADMIN")'
        )
    ],
    normalizationContext: ['groups' => ['rate:read']],
    denormalizationContext: ['groups' => ['rate:write']],
    order: ['id' => 'DESC'],
    paginationEnabled: false
)]
class Rate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['rate:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 3, enumType: Currency::class)]
    #[Assert\NotNull(message: 'From currency cannot be null')]
    #[Groups(['rate:read', 'rate:write'])]
    private ?Currency $fromCurrency = null;

    #[ORM\Column(length: 3, enumType: Currency::class)]
    #[Assert\NotNull(message: 'To currency cannot be null')]
    #[Groups(['rate:read', 'rate:write'])]
    private ?Currency $toCurrency = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 6)]
    #[Assert\NotBlank(message: 'Rate cannot be empty')]
    #[Assert\Positive(message: 'Rate must be positive')]
    #[Groups(['rate:read', 'rate:write'])]
    private ?string $rate = null;

    #[ORM\Column]
    #[Groups(['rate:read', 'rate:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['rate:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['rate:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromCurrency(): ?Currency
    {
        return $this->fromCurrency;
    }

    public function setFromCurrency(Currency $fromCurrency): static
    {
        $this->fromCurrency = $fromCurrency;
        return $this;
    }

    public function getToCurrency(): ?Currency
    {
        return $this->toCurrency;
    }

    public function setToCurrency(Currency $toCurrency): static
    {
        $this->toCurrency = $toCurrency;
        return $this;
    }

    public function getRate(): ?string
    {
        return $this->rate;
    }

    public function setRate(string $rate): static
    {
        $this->rate = $rate;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Convert an amount from this rate's fromCurrency to toCurrency
     */
    public function convert(float $amount): float
    {
        return $amount * (float) $this->rate;
    }

    /**
     * Convert an amount in reverse (from toCurrency to fromCurrency)
     */
    public function convertReverse(float $amount): float
    {
        if ((float) $this->rate === 0.0) {
            return 0.0;
        }
        return $amount / (float) $this->rate;
    }

    public function __toString(): string
    {
        return sprintf('%s → %s: %s',
            $this->fromCurrency?->value ?? '?',
            $this->toCurrency?->value ?? '?',
            $this->rate ?? '?'
        );
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
