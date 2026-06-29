<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\CheckInRepository;
use App\State\Processor\CheckInProcessor;
use App\State\Processor\CheckoutProcessor;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CheckInRepository::class)]
#[ApiResource(
    operations: [
        // Collection filtrée par CurrentUserExtension : chacun ne voit que ses check-ins.
        new GetCollection(
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            security: "is_granted('ROLE_USER') and object.getUser() == user or is_granted('ROLE_ADMIN')"
        ),
        // Création : l'utilisateur et l'heure sont posés côté serveur, et l'abonnement
        // actif est vérifié (CheckInProcessor). Empêche un check-in sans abonnement.
        new Post(
            security: "is_granted('ROLE_USER')",
            processor: CheckInProcessor::class
        ),
        // Check-out : action sur un check-in existant (vérifie la propriété).
        new Post(
            uriTemplate: '/check_ins/{id}/checkout',
            security: "is_granted('ROLE_USER') and object.getUser() == user or is_granted('ROLE_ADMIN')",
            input: false,
            read: true,
            processor: CheckoutProcessor::class,
            name: 'checkin_checkout'
        )
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

    // Posé côté serveur par CheckInProcessor (jamais accepté depuis la requête).
    #[ORM\ManyToOne(inversedBy: 'checkIns')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['checkin:read'])]
    private ?User $user = null;

    // Posé par le constructeur / le processor.
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'Check-in time cannot be null')]
    #[Groups(['checkin:read'])]
    private ?DateTimeInterface $checkedInAt = null;

    // Posé par l'opération de check-out.
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['checkin:read'])]
    private ?DateTimeInterface $checkedOutAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['checkin:read', 'checkin:write'])]
    private ?string $location = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['checkin:read', 'checkin:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['checkin:read'])]
    private ?DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->checkedInAt = new DateTime();
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

    public function getCheckedInAt(): ?DateTimeInterface
    {
        return $this->checkedInAt;
    }

    public function setCheckedInAt(DateTimeInterface $checkedInAt): static
    {
        $this->checkedInAt = $checkedInAt;
        return $this;
    }

    public function getCheckedOutAt(): ?DateTimeInterface
    {
        return $this->checkedOutAt;
    }

    public function setCheckedOutAt(?DateTimeInterface $checkedOutAt): static
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

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}

