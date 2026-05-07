<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Enum\TranslationStatus;
use App\Repository\TranslationRequestRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TranslationRequestRepository::class)]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/translation-requests'
        ),
    ],
    normalizationContext: ['groups' => ['translation:read']],
    denormalizationContext: ['groups' => ['translation:write']]
)]
#[ORM\HasLifecycleCallbacks]
class TranslationRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['translation:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Name is required')]
    #[Groups(['translation:read', 'translation:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Invalid email format')]
    #[Groups(['translation:read', 'translation:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['translation:read', 'translation:write'])]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Document type is required')]
    #[Groups(['translation:read', 'translation:write'])]
    private ?string $documentType = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Source language is required')]
    #[Groups(['translation:read', 'translation:write'])]
    private ?string $sourceLanguage = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Target language is required')]
    #[Groups(['translation:read', 'translation:write'])]
    private ?string $targetLanguage = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Deadline is required')]
    #[Groups(['translation:read', 'translation:write'])]
    private ?string $deadline = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Message is required')]
    #[Groups(['translation:read', 'translation:write'])]
    private ?string $message = null;

    #[ORM\Column(type: 'string', enumType: TranslationStatus::class)]
    #[Groups(['translation:read'])]
    private TranslationStatus $status = TranslationStatus::PENDING;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['translation:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['translation:read'])]
    private ?DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTime();
    }

    // Getters and Setters
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
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

    public function getDocumentType(): ?string
    {
        return $this->documentType;
    }

    public function setDocumentType(string $documentType): static
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function getSourceLanguage(): ?string
    {
        return $this->sourceLanguage;
    }

    public function setSourceLanguage(string $sourceLanguage): static
    {
        $this->sourceLanguage = $sourceLanguage;
        return $this;
    }

    public function getTargetLanguage(): ?string
    {
        return $this->targetLanguage;
    }

    public function setTargetLanguage(string $targetLanguage): static
    {
        $this->targetLanguage = $targetLanguage;
        return $this;
    }

    public function getDeadline(): ?string
    {
        return $this->deadline;
    }

    public function setDeadline(string $deadline): static
    {
        $this->deadline = $deadline;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getStatus(): TranslationStatus
    {
        return $this->status;
    }

    public function setStatus(TranslationStatus|string $status): static
    {
        if (is_string($status)) {
            $status = TranslationStatus::fromString($status);
        }
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
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
}

