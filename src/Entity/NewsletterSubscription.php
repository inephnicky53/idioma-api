<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Repository\NewsletterSubscriptionRepository;
use App\State\Processor\NewsletterSubscriptionProcessor;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NewsletterSubscriptionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_NEWSLETTER_EMAIL', columns: ['email'])]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/newsletter/subscribe',
            processor: NewsletterSubscriptionProcessor::class
        ),
    ],
    normalizationContext: ['groups' => ['newsletter:read']],
    denormalizationContext: ['groups' => ['newsletter:write']]
)]
class NewsletterSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['newsletter:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Invalid email format')]
    #[Groups(['newsletter:read', 'newsletter:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(
        choices: ['active', 'inactive', 'unsubscribed'],
        message: 'Status must be one of: active, inactive, unsubscribed'
    )]
    #[Groups(['newsletter:read'])]
    private string $status = 'active';

    #[ORM\Column(type: 'datetime')]
    #[Groups(['newsletter:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['newsletter:read'])]
    private ?DateTimeInterface $unsubscribedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->status = 'active';
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new DateTime();
        }
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUnsubscribedAt(): ?DateTimeInterface
    {
        return $this->unsubscribedAt;
    }

    public function setUnsubscribedAt(?DateTimeInterface $unsubscribedAt): static
    {
        $this->unsubscribedAt = $unsubscribedAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isUnsubscribed(): bool
    {
        return $this->status === 'unsubscribed';
    }
}

