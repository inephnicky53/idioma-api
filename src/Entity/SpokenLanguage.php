<?php

namespace App\Entity;

use App\Repository\SpokenLanguageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Intl\Languages;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SpokenLanguageRepository::class)]
class SpokenLanguage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['teacher:list'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'spokenLanguages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Teacher $teacher = null;

    #[ORM\Column(length: 15)]
    #[Groups(['teacher:become', 'teacher:list'])]
    private ?string $level = null;

    #[ORM\Column(length: 255)]
    #[Groups(['teacher:become', 'teacher:list'])]
    private ?string $language = null;

    public function __construct()
    {
    }

    public function __toString(): string
    {
        //return Languages::getName($this->language) . " ({$this->level})";
        return $this->language . " ({$this->level})";
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }
}
