<?php

namespace App\Entity;

use App\Repository\TeachingLanguageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TeachingLanguageRepository::class)]
class TeachingLanguage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['teacher:show', 'teacher:become', 'teacher:certifications'])]
    private ?Language $language = null;

    #[ORM\ManyToMany(targetEntity: Language::class)]
    #[Groups(['teacher:show', 'teacher:become', 'teacher:certifications'])]
    private Collection $categories;

    #[ORM\ManyToOne(inversedBy: 'teachingLanguages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Teacher $teacher = null;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->language;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLanguage(): ?Language
    {
        return $this->language;
    }

    public function setLanguage(?Language $language): static
    {
        $this->language = $language;

        return $this;
    }

    /**
     * @return Collection<int, Language>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Language $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Language $category): static
    {
        $this->categories->removeElement($category);

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
}
