<?php

namespace App\Entity;

use App\Repository\TeacherFormationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TeacherFormationRepository::class)]
class TeacherFormation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['teacher:show'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'teacherFormations')]
    private ?Teacher $teacher = null;

    #[ORM\Column(length: 255)]
    #[Groups(['teacher:show', 'teacher:certifications'])]
    private ?string $university = null;

    #[ORM\Column(length: 255)]
    #[Groups(['teacher:show', 'teacher:certifications'])]
    private ?string $certificate = null;

    #[ORM\Column(length: 255)]
    #[Groups(['teacher:show', 'teacher:certifications'])]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['teacher:show', 'teacher:certifications'])]
    private ?string $speciality = null;

    #[ORM\Column]
    #[Groups(['teacher:show', 'teacher:certifications'])]
    private ?int $yearStart = null;

    #[ORM\Column]
    #[Groups(['teacher:show', 'teacher:certifications'])]
    private ?int $yearEnd = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[Groups(['teacher:show'])]
    private ?Attachment $file = null;

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

    public function getUniversity(): ?string
    {
        return $this->university;
    }

    public function setUniversity(string $university): static
    {
        $this->university = $university;

        return $this;
    }

    public function getCertificate(): ?string
    {
        return $this->certificate;
    }

    public function setCertificate(string $certificate): static
    {
        $this->certificate = $certificate;

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

    public function getSpeciality(): ?string
    {
        return $this->speciality;
    }

    public function setSpeciality(?string $speciality): static
    {
        $this->speciality = $speciality;

        return $this;
    }

    public function getYearStart(): ?int
    {
        return $this->yearStart;
    }

    public function setYearStart(int $start): static
    {
        $this->yearStart = $start;

        return $this;
    }

    public function getYearEnd(): ?int
    {
        return $this->yearEnd;
    }

    public function setYearEnd(int $end): static
    {
        $this->yearEnd = $end;

        return $this;
    }

    public function getFile(): ?Attachment
    {
        return $this->file;
    }

    public function setFile(?Attachment $file): static
    {
        $this->file = $file;

        return $this;
    }
}
