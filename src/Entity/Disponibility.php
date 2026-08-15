<?php

namespace App\Entity;

use App\Repository\DisponibilityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: DisponibilityRepository::class)]
class Disponibility
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['teacher:show'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['teacher:list', 'teacher:show', 'teacher:disponibilities'])]
    private ?string $day = null;

    #[ORM\Column]
    #[Groups(['teacher:list', 'teacher:show', 'teacher:disponibilities'])]
    private ?string $start = null;

    #[ORM\Column]
    #[Groups(['teacher:list', 'teacher:show', 'teacher:disponibilities'])]
    private ?string $end = null;

    #[ORM\ManyToOne(inversedBy: 'disponibilities')]
    private ?Teacher $teacher = null;

    #[ORM\Column]
    #[Groups(['teacher:list', 'teacher:show'])]
    private ?bool $isActive;

    public function __construct()
    {
        $this->isActive = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDay(): ?string
    {
        return $this->day;
    }

    public function setDay(string $day): static
    {
        $this->day = $day;

        return $this;
    }

    public function getStart(): ?string
    {
        return $this->start;
    }

    public function setStart(string $start): static
    {
        $this->start = $start;

        return $this;
    }

    public function getEnd(): ?string
    {
        return $this->end;
    }

    public function setEnd(string $end): static
    {
        $this->end = $end;

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

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
