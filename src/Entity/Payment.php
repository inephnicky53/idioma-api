<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Contract\PayableInterface;
use App\Dto\CreatePaymentDto;
use App\Enum\Currency;
use App\Enum\PaymentMethod;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PurchaseType;
use App\Repository\PaymentRepository;
use App\State\Processor\CheckTransactionProcessor;
use App\State\Processor\PaymentProcessor;
use App\State\Processor\ValidatePaymentProcessor;
use App\State\Provider\PendingCoursePaymentProvider;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Payment',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['payment:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new GetCollection(
            uriTemplate: '/payments/pending-courses',
            description: 'Récupère les paiements de cours en attente',
            normalizationContext: ['groups' => ['payment:read']],
            security: "is_granted('ROLE_USER')",
            name: 'get_pending_course_payments',
            provider: PendingCoursePaymentProvider::class
        ),
        new Get(
            normalizationContext: ['groups' => ['payment:read']],
            security: "is_granted('ROLE_USER') and object.getUser() == user"
        ),
        new Post(
            security: "is_granted('ROLE_USER')",
            validationContext: ['groups' => ['Default', 'payment:create']],
            input: CreatePaymentDto::class,
            processor: PaymentProcessor::class
        ),
        new Patch(
            uriTemplate: '/payments/{id}/validate',
            description: 'Valider un paiement (admin uniquement) - marque comme complété et active l\'achat',
            normalizationContext: ['groups' => ['payment:read']],
            security: "is_granted('ROLE_ADMIN')",
            name: 'validate_payment',
            processor: ValidatePaymentProcessor::class
        ),
        new Patch(
            uriTemplate: '/payments/{id}/check-transaction',
            description: 'Vérifier le statut d\'une transaction auprès du provider de paiement',
            normalizationContext: ['groups' => ['payment:read']],
            security: "is_granted('ROLE_USER') and object.getUser() == user or is_granted('ROLE_ADMIN')",
            name: 'check_transaction',
            processor: CheckTransactionProcessor::class
        ),
        new Delete(
            uriTemplate: '/payments/{id}/cancel',
            description: 'Annuler un paiement en attente (utilisateur ou admin)',
            security: "is_granted('ROLE_USER') and object.getUser() == user or is_granted('ROLE_ADMIN')",
            name: 'cancel_payment'
        ),
    ],
    paginationEnabled: true
)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['payment:read', 'user:read'])]
    private ?int $id = null;

    // User est défini automatiquement par le PaymentProcessor via le JWT
    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;
    #[ORM\Column(length: 20, enumType: PurchaseType::class)]
    #[Groups(['payment:read'])]
    private PurchaseType $purchaseType = PurchaseType::SUBSCRIPTION_CLUB;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['payment:write'])]
    private ?SubscriptionPlan $subscriptionPlan = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['payment:read', 'payment:write'])]
    private ?Course $course = null;

    // Amount est calculé automatiquement par le PaymentProcessor depuis le plan
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['payment:read'])]
    private ?string $amount = null;

    #[ORM\Column(length: 3, nullable: true)]
    #[Groups(['payment:read', 'payment:write'])]
    private ?Currency $currency = null;

    #[ORM\Column(length: 50)]
    #[Groups(['payment:read'])]
    private ?PaymentStatus $status = PaymentStatus::WAIT;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['payment:read', 'payment:write'])]
    private ?PaymentMethod $paymentMethod = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $paidAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['payment:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    private bool $isSmsSend = false;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $responsedAt;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['payment:read'])]
    private ?string $phone = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['payment:read'])]
    private ?string $reference = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $providerReference;

    #[ORM\Column(length: 20, nullable: true, enumType: PaymentProvider::class)]
    private ?PaymentProvider $provider = PaymentProvider::FLEXPAY;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[ApiProperty(readable: false)]
    private ?array $data = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPurchaseType(): PurchaseType
    {
        return $this->purchaseType;
    }

    public function setPurchaseType(PurchaseType $purchaseType): static
    {
        $this->purchaseType = $purchaseType;
        return $this;
    }

    /**
     * Retourne le label du type d'achat (pour EasyAdmin)
     */
    public function getPurchaseTypeLabel(): string
    {
        return $this->purchaseType->getLabel();
    }

    public function getSubscriptionPlan(): ?SubscriptionPlan
    {
        return $this->subscriptionPlan;
    }

    public function setSubscriptionPlan(?SubscriptionPlan $subscriptionPlan): static
    {
        $this->subscriptionPlan = $subscriptionPlan;
        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;
        return $this;
    }

    /**
     * Retourne l'objet Payable (plan ou cours)
     */
    #[ApiProperty(readable: false)]
    public function getPayable(): ?PayableInterface
    {
        if ($this->purchaseType === PurchaseType::COURSE && $this->course) {
            return $this->course;
        }
        return $this->subscriptionPlan;
    }

    /**
     * Définit le produit à payer et configure automatiquement le type, montant et devise
     */
    public function setPayable(PayableInterface $payable): static
    {
        $this->purchaseType = $payable->getPurchaseType();
        $this->amount = $payable->getPrice();
        $this->currency = $payable->getCurrency();

        if ($payable instanceof Course) {
            $this->course = $payable;
            $this->subscriptionPlan = null;
        } elseif ($payable instanceof SubscriptionPlan) {
            $this->subscriptionPlan = $payable;
            $this->course = null;
        }

        return $this;
    }

    /**
     * Retourne le nom du produit acheté (plan ou cours)
     */
    public function getProduct(): ?string
    {
        return $this->getPayable()?->getLabel();
    }

    /**
     * Retourne l'URL de paiement pour les cartes bancaires
     */
    #[Groups(['payment:read'])]
    public function getPaymentUrl(): ?string
    {
        return $this->data['paymentUrl'] ?? null;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getStatus(): ?PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Retourne les informations complètes du statut pour l'API
     */
    #[Groups(['payment:read'])]
    public function getStatusInfo(): array
    {
        return $this->status?->toArray() ?? [];
    }

    /**
     * Retourne le label du statut (pour EasyAdmin)
     */
    public function getStatusLabel(): string
    {
        return $this->status?->getLabel() ?? 'Inconnu';
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    /**
     * Retourne le label de la méthode de paiement (pour EasyAdmin)
     */
    public function getPaymentMethodLabel(): string
    {
        return $this->paymentMethod?->getLabel() ?? 'Non défini';
    }

    public function setPaymentMethod(?PaymentMethod $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getPaidAt(): ?DateTimeInterface
    {
        return $this->paidAt;
    }

    public function setPaidAt(?DateTimeInterface $paidAt): static
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTime();
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

    public function isSmsSend(): bool
    {
        return $this->isSmsSend;
    }

    public function setIsSmsSend(bool $isSmsSend): static
    {
        $this->isSmsSend = $isSmsSend;
        return $this;
    }

    public function getResponsedAt(): ?DateTimeImmutable
    {
        return $this->responsedAt;
    }

    public function setResponsedAt(?DateTimeImmutable $responsedAt): static
    {
        $this->responsedAt = $responsedAt;

        return $this;
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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getProviderReference(): ?string
    {
        return $this->providerReference;
    }

    public function setProviderReference(?string $providerReference): void
    {
        $this->providerReference = $providerReference;
    }

    public function getProvider(): ?PaymentProvider
    {
        return $this->provider;
    }

    public function setProvider(?PaymentProvider $provider): void
    {
        $this->provider = $provider;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Retourne les données JSON formatées pour l'admin
     */
    public function getDataFormatted(): string
    {
        return $this->data ? json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    }
}

