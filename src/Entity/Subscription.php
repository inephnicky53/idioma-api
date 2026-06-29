<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\Repository\SubscriptionRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        // Collection filtrée par CurrentUserExtension : chaque utilisateur ne voit
        // que ses propres abonnements (les admins voient tout).
        new GetCollection(
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            security: "is_granted('ROLE_USER') and object.getUser() == user or is_granted('ROLE_ADMIN')"
        ),
        // Les abonnements sont créés côté serveur après paiement (PaymentManager).
        // L'écriture directe est réservée aux administrateurs pour éviter qu'un
        // utilisateur ne s'octroie un abonnement actif sans payer.
        new Post(
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN')"
        )
    ],
    normalizationContext: ['groups' => ['subscription:read']],
    denormalizationContext: ['groups' => ['subscription:write']]
)]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['subscription:read', 'user:read', 'payment:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'User cannot be null')]
    #[Groups(['subscription:read', 'subscription:write'])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Plan cannot be null')]
    #[Groups(['subscription:read', 'subscription:write'])]
    private ?SubscriptionPlan $plan = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'Start date cannot be null')]
    #[Groups(['subscription:read', 'subscription:write', 'user:read'])]
    private ?DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'End date cannot be null')]
    #[Groups(['subscription:read', 'subscription:write', 'user:read'])]
    private ?DateTimeInterface $endDate = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(
        choices: ['active', 'inactive', 'pending', 'expired', 'cancelled'],
        message: 'Status must be one of: active, inactive, pending, expired, cancelled'
    )]
    #[Groups(['subscription:read', 'subscription:write', 'user:read'])]
    private ?string $status = 'pending';

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Sessions used must be positive or zero')]
    #[Groups(['subscription:read', 'user:read'])]
    private int $sessionsUsed = 0;

    #[ORM\Column]
    #[Groups(['subscription:read'])]
    private bool $autoRenew = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['subscription:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['subscription:read'])]
    private ?DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->status = 'pending';
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

    public function getPlan(): ?SubscriptionPlan
    {
        return $this->plan;
    }

    public function setPlan(?SubscriptionPlan $plan): static
    {
        $this->plan = $plan;
        return $this;
    }

    public function getStartDate(): ?DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getSessionsUsed(): int
    {
        return $this->sessionsUsed;
    }

    public function setSessionsUsed(int $sessionsUsed): static
    {
        $this->sessionsUsed = $sessionsUsed;
        return $this;
    }

    public function isAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    public function setAutoRenew(bool $autoRenew): static
    {
        $this->autoRenew = $autoRenew;
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

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->endDate > new DateTime();
    }

    public function getDaysRemaining(): int
    {
        if (!$this->isActive()) {
            return 0;
        }
        $now = new DateTime();
        $interval = $this->endDate->diff($now);
        return (int) $interval->format('%a');
    }

    public function getSessionsRemaining(): int
    {
        if ($this->plan === null) {
            return 0;
        }
        return max(0, $this->plan->getSessionsLimit() - $this->sessionsUsed);
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTime();
    }
}

