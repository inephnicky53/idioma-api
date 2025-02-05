<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\CreatePaymentInput;
use App\Idioma;
use App\Repository\PaymentRepository;
use App\State\Payment\InitiatePaymentProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            input: CreatePaymentInput::class,
            processor: InitiatePaymentProcessor::class,
        ),
        new Patch(),
    ],
    normalizationContext: ['groups' => ['payment:get']],
)]
class Payment
{
    const OPERATOR_BANK = 'PAYIN-BNK';
    const OPERATOR_MOBILE = 'PAYIN-MOB';
    const OPERATOR_PAYPAL = 'PAYPAL';

    const TYPE_DEMAND = 'DEMAND';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['payment:get'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['payment:get'])]
    private ?string $reference = null;

    #[ORM\Column]
    #[Groups(['payment:get'])]
    private ?float $amount = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payment:get'])]
    private ?Currency $currency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payment:get'])]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    #[Groups(['payment:get'])]
    private ?string $method = null;

    #[ORM\Column(length: 255)]
    #[Groups(['payment:get'])]
    private ?string $type = null;

    #[ORM\Column(length: 1)]
    #[Groups(['payment:get'])]
    private ?string $status = null;

    #[ORM\ManyToOne]
    #[Groups(['payment:get'])]
    private ?User $executedBy = null;

    #[ORM\Column]
    #[Groups(['payment:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['payment:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['payment:get'])]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $methodData = null;

    public function __construct()
    {
        if (is_null($this->createdAt)) $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public static function getTypeList(): array
    {
        return [
            'Demande de paiement' => self::TYPE_DEMAND,
        ];
    }

    public static function getTypeListForView(): array
    {
        return array_flip(self::getTypeList());
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public static function getMethodList(): array
    {
        return [
            'Mobile money' => self::OPERATOR_MOBILE,
            'Paypal' => self::OPERATOR_PAYPAL,
            'Carte bancaire' => self::OPERATOR_BANK,
        ];
    }

    public static function getMethodListForView(): array
    {
        return array_flip(self::getMethodList());
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public static function getStatusList(): array
    {
        return [
            'Payé' => Idioma::STATUS_SUCCESS,
            'Refusé' => Idioma::STATUS_FAILED,
            'Demande' => Idioma::STATUS_CREATED,
        ];
    }

    public static function getStatusListForView(): array
    {
        return array_flip(self::getStatusList());
    }

    public static function getStatusBadge(): array
    {
        return [
            'success',
            'danger',
            'warning',
        ];
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getExecutedBy(): ?User
    {
        return $this->executedBy;
    }

    public function setExecutedBy(?User $executedBy): static
    {
        $this->executedBy = $executedBy;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getMethodData(): ?string
    {
        return $this->methodData;
    }

    public function setMethodData(?string $methodData): static
    {
        $this->methodData = $methodData;

        return $this;
    }
}
