<?php

namespace App\Entity;

use App\Repository\TeacherCertificationRepository;
use App\Trait\Datable;
use App\Trait\Verifiable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TeacherCertificationRepository::class)]
class TeacherCertification
{
    use Datable;
    use Verifiable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['teacher:show'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'teacherCertifications')]
    private ?Teacher $teacher = null;

    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['teacher:show', 'teacher:certifications'])]
    private ?string $certification = null;

    #[ORM\ManyToMany(targetEntity: Language::class)]
    #[Groups(['teacher:show', 'teacher:certifications'])]
    private Collection $language;

    #[ORM\Column]
    #[Groups(['teacher:show', 'teacher:certifications'])]
    private ?\DateTimeImmutable $yearStart = null;

    #[ORM\Column]
    #[Groups(['teacher:show', 'teacher:certifications'])]
    private ?\DateTimeImmutable $yearEnd = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Attachment $file = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['teacher:show', 'teacher:certifications'])]
    private ?string $proofImage = null;

    public function __construct()
    {
        $this->language = new ArrayCollection();
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

    public function getCertification(): ?string
    {
        return $this->certification;
    }

    public function setCertification(?string $certification): static
    {
        $this->certification = $certification;

        return $this;
    }

    /**
     * @return Collection<int, Language>
     */
    public function getLanguage(): Collection
    {
        return $this->language;
    }

    public function addLanguage(Language $language): static
    {
        if (!$this->language->contains($language)) {
            $this->language->add($language);
        }

        return $this;
    }

    public function removeLanguage(Language $language): static
    {
        $this->language->removeElement($language);

        return $this;
    }

    public function getYearStart(): ?\DateTimeImmutable
    {
        return $this->yearStart;
    }

    public function setYearStart(?\DateTimeImmutable $start): static
    {
        $this->yearStart = $start;

        return $this;
    }

    public function getYearEnd(): ?\DateTimeImmutable
    {
        return $this->yearEnd;
    }

    public function setYearEnd(?\DateTimeImmutable $end): static
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

    public function getProofImage(): ?string
    {
        return $this->proofImage;
    }

    public function setProofImage(?string $proofImage): static
    {
        $this->proofImage = $proofImage;

        return $this;
    }
}
