<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\SubscriptionPlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SubscriptionPlanRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['subscriptionplan:read']],
    denormalizationContext: ['groups' => ['subscriptionplan:write']]
)]
class SubscriptionPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['subscriptionplan:read', 'subscription:read', 'payment:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Name cannot be empty')]
    #[Groups(['subscriptionplan:read', 'subscriptionplan:write', 'subscription:read', 'payment:read'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['subscriptionplan:read', 'subscriptionplan:write', 'subscription:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Price cannot be empty')]
    #[Assert\Positive(message: 'Price must be positive')]
    #[Groups(['subscriptionplan:read', 'subscriptionplan:write', 'subscription:read', 'payment:read'])]
    private ?string $price = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Duration in days cannot be empty')]
    #[Assert\Positive(message: 'Duration must be positive')]
    #[Groups(['subscriptionplan:read', 'subscriptionplan:write', 'subscription:read'])]
    private ?int $durationDays = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Type cannot be empty')]
    #[Assert\Choice(
        choices: ['club', 'formation', 'both'],
        message: 'Type must be one of: club, formation, both'
    )]
    #[Groups(['subscriptionplan:read', 'subscriptionplan:write', 'subscription:read', 'payment:read'])]
    private ?string $type = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Sessions limit cannot be null')]
    #[Assert\PositiveOrZero(message: 'Sessions limit must be positive or zero')]
    #[Groups(['subscriptionplan:read', 'subscriptionplan:write', 'subscription:read'])]
    private ?int $sessionsLimit = null;

    #[ORM\Column]
    #[Groups(['subscriptionplan:read', 'subscriptionplan:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['subscriptionplan:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['subscriptionplan:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, Subscription>
     */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'plan')]
    private Collection $subscriptions;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->subscriptions = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getDurationDays(): ?int
    {
        return $this->durationDays;
    }

    public function setDurationDays(int $durationDays): static
    {
        $this->durationDays = $durationDays;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getSessionsLimit(): ?int
    {
        return $this->sessionsLimit;
    }

    public function setSessionsLimit(int $sessionsLimit): static
    {
        $this->sessionsLimit = $sessionsLimit;
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
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): static
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setPlan($this);
        }
        return $this;
    }

    public function removeSubscription(Subscription $subscription): static
    {
        if ($this->subscriptions->removeElement($subscription)) {
            if ($subscription->getPlan() === $this) {
                $subscription->setPlan(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}

