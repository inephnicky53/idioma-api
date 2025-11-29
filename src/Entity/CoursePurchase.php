<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\CoursePurchaseRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CoursePurchaseRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'unique_user_course', columns: ['user_id', 'course_id'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
    ],
    normalizationContext: ['groups' => ['course_purchase:read']],
)]
class CoursePurchase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['course_purchase:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['course_purchase:read'])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'purchases')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['course_purchase:read'])]
    private ?Course $course = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['course_purchase:read'])]
    private ?Payment $payment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['course_purchase:read'])]
    private ?DateTimeInterface $purchasedAt;

    #[ORM\Column]
    #[Groups(['course_purchase:read'])]
    private bool $isActive = true;

    public function __construct()
    {
        $this->purchasedAt = new DateTime();
    }

    public function __toString(): string
    {
        $user = $this->user?->getFullName() ?? 'Utilisateur';
        $course = $this->course?->getTitle() ?? 'Cours';
        return "$user - $course";
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

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;
        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): static
    {
        $this->payment = $payment;
        return $this;
    }

    public function getPurchasedAt(): ?DateTimeInterface
    {
        return $this->purchasedAt;
    }

    public function setPurchasedAt(DateTimeInterface $purchasedAt): static
    {
        $this->purchasedAt = $purchasedAt;
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
}

