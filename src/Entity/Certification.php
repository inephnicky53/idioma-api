<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\CertificationRepository;
use App\Trait\Datable;
use App\Trait\Deletable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CertificationRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection,
        new Post
    ],
    normalizationContext: ['groups' => 'certification:get']
)]
class Certification
{
    use Datable {
        Datable::__construct as private dateConstructor;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['certification:get'])]
    private ?int $id = null;

    #[ORM\ManyToMany(targetEntity: Language::class)]
    #[Groups(['certification:get'])]
    private Collection $languages;

    #[ORM\Column(length: 255)]
    #[Groups(['certification:get'])]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'certification', targetEntity: TeacherCertification::class, orphanRemoval: true)]
    private Collection $teacherCertifications;

    public function __construct()
    {
        $this->dateConstructor();
        $this->languages = new ArrayCollection();
        $this->teacherCertifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Language>
     */
    public function getLanguages(): Collection
    {
        return $this->languages;
    }

    public function addLanguage(Language $language): static
    {
        if (!$this->languages->contains($language)) {
            $this->languages->add($language);
        }

        return $this;
    }

    public function removeLanguage(Language $language): static
    {
        $this->languages->removeElement($language);

        return $this;
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

    /**
     * @return Collection<int, TeacherCertification>
     */
    public function getTeacherCertifications(): Collection
    {
        return $this->teacherCertifications;
    }

    public function addTeacherCertification(TeacherCertification $teacherCertification): static
    {
        if (!$this->teacherCertifications->contains($teacherCertification)) {
            $this->teacherCertifications->add($teacherCertification);
            $teacherCertification->setCertification($this);
        }

        return $this;
    }

    public function removeTeacherCertification(TeacherCertification $teacherCertification): static
    {
        if ($this->teacherCertifications->removeElement($teacherCertification)) {
            // set the owning side to null (unless already changed)
            if ($teacherCertification->getCertification() === $this) {
                $teacherCertification->setCertification(null);
            }
        }

        return $this;
    }
}
