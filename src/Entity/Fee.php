<?php

namespace App\Entity;

use App\Repository\FeeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FeeRepository::class)]
class Fee
{
    public const TYPES = [
        'MOBILE' => self::FEE_TRANSACTION_MOBILE,
        'BANK' => self::FEE_TRANSACTION_BANK,
        'SERVICE' => self::FEE_SERVICE,
    ];

    public const FEE_TRANSACTION_MOBILE = "M";
    public const FEE_TRANSACTION_BANK = "B";
    public const FEE_SERVICE = "S";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "The name cannot be blank.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "The name cannot exceed {{ limit }} characters."
    )]
    private ?string $name = null;

    #[ORM\Column(length: 1)]
    #[Assert\NotBlank(message: "The type cannot be blank.")]
    #[Assert\Choice(
        choices: [self::FEE_TRANSACTION_MOBILE, self::FEE_TRANSACTION_BANK, self::FEE_SERVICE],
        message: "Invalid fee type."
    )]
    private ?string $type = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "The value cannot be blank.")]
    #[Assert\Positive(message: "The value must be positive.")]
    private ?float $value = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: "The minimum value must be zero or positive.")]
    private ?float $min = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: "The maximum value must be zero or positive.")]
    private ?float $max = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public static function getTypes(): array
    {
        return [
            self::FEE_TRANSACTION_BANK => 'Frais de transaction bancaire',
            self::FEE_TRANSACTION_MOBILE => 'Frais de transaction mobile',
            self::FEE_SERVICE => 'Frais de service',
        ];
    }

    public function setType(string $type): static
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException("Invalid fee type.");
        }

        $this->type = $type;

        return $this;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(float $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getMin(): ?float
    {
        return $this->min;
    }

    public function setMin(?float $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function getMax(): ?float
    {
        return $this->max;
    }

    public function setMax(?float $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isWithinRange(?float $amount): bool
    {
        if ($amount === null)
            return false;

        if ($this->min !== null && $amount < $this->min)
            return false;

        if ($this->max !== null && $amount > $this->max)
            return false;

        return true;
    }
}
