<?php

namespace App\Entity;

use App\Repository\UserTeacherRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UserTeacherRepository::class)]
class UserTeacher
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:teachers'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'teachers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'students')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user:teachers'])]
    private ?Teacher $teacher = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['user:teachers'])]
    private ?float $hours = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $buyedAt = null;

    public function __construct()
    {
        if (is_null($this->createdAt))
            $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->getTeacher();
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

    public function getTeacher(): ?Teacher
    {
        return $this->teacher;
    }

    public function setTeacher(?Teacher $teacher): static
    {
        $this->teacher = $teacher;

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

    public function getHours(): float
    {
        return $this->hours;
    }

    public function setHours(float $hours): static
    {
        $this->hours = $hours;

        return $this;
    }

    public function addHours(float $hours): static
    {
        $this->hours += $hours;

        return $this;
    }

    public function getBuyedAt(): ?\DateTimeImmutable
    {
        return $this->buyedAt;
    }

    public function setBuyedAt(?\DateTimeImmutable $buyedAt): static
    {
        $this->buyedAt = $buyedAt;

        return $this;
    }
}
