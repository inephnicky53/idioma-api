<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\CheckInRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CheckInRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post()
    ],
    normalizationContext: ['groups' => ['checkin:read']],
    denormalizationContext: ['groups' => ['checkin:write']]
)]
class CheckIn
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['checkin:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'checkIns')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'User cannot be null')]
    #[Groups(['checkin:read', 'checkin:write'])]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'Check-in time cannot be null')]
    #[Groups(['checkin:read', 'checkin:write'])]
    private ?\DateTimeInterface $checkedInAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['checkin:read', 'checkin:write'])]
    private ?\DateTimeInterface $checkedOutAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['checkin:read', 'checkin:write'])]
    private ?string $location = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['checkin:read', 'checkin:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['checkin:read'])]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->checkedInAt = new \DateTime();
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

    public function getCheckedInAt(): ?\DateTimeInterface
    {
        return $this->checkedInAt;
    }

    public function setCheckedInAt(\DateTimeInterface $checkedInAt): static
    {
        $this->checkedInAt = $checkedInAt;
        return $this;
    }

    public function getCheckedOutAt(): ?\DateTimeInterface
    {
        return $this->checkedOutAt;
    }

    public function setCheckedOutAt(?\DateTimeInterface $checkedOutAt): static
    {
        $this->checkedOutAt = $checkedOutAt;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}

