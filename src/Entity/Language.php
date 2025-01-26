<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Odm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\LanguageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: LanguageRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['language:list']]
)]
#[ApiFilter(OrderFilter::class, properties: ['name' => 'DESC'])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive'])]
class Language
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['language:list', 'teacher:list', 'user:courses', 'user:me', 'certification:get'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['course:list', 'language:list', 'teacher:list', 'user:courses', 'user:me', 'certification:get'])]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'language', targetEntity: Course::class, orphanRemoval: true)]
    private Collection $courses;

    #[ORM\Column(length: 2, nullable: true)]
    #[Groups(['course:list', 'language:list'])]
    private ?string $flag = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['course:list', 'language:list'])]
    private ?string $locale = null;

    #[ORM\Column]
    #[Groups(['course:list', 'language:list'])]
    private ?bool $isActive = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['language:list'])]
    private ?bool $isPublic = false;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['language:list'])]
    private ?int $teachers = 0;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
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
     * @return Collection<int, Course>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(Course $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
            $course->setLanguage($this);
        }

        return $this;
    }

    public function removeCourse(Course $course): static
    {
        if ($this->courses->removeElement($course)) {
            // set the owning side to null (unless already changed)
            if ($course->getLanguage() === $this) {
                $course->setLanguage(null);
            }
        }

        return $this;
    }

    public function getFlag(): ?string
    {
        return $this->flag;
    }

    public function setFlag(?string $flag): static
    {
        $this->flag = $flag;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;

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

    public function isPublic(): ?bool
    {
        return $this->isPublic;
    }

    public function setPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    public function getTeachers(): ?int
    {
        return $this->teachers;
    }

    public function setTeachers(int $teachers): static
    {
        $this->teachers = $teachers;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
