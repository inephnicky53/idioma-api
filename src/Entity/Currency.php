<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\CurrencyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['currency:list']]
)]
class Currency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['currency:list', 'rate:list', 'course:list', 'teacher:list'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['currency:list', 'rate:list'])]
    private ?string $name = null;

    #[ORM\Column(length: 3)]
    #[Groups(['currency:list', 'rate:list', 'course:list', 'teacher:list'])]
    private ?string $min = null;

    #[ORM\OneToMany(targetEntity: Rate::class, mappedBy: 'currency', orphanRemoval: true)]
    private Collection $rates;

    #[ORM\OneToMany(targetEntity: Teacher::class, mappedBy: 'currency')]
    private Collection $teachers;

    public function __construct()
    {
        $this->rates = new ArrayCollection();
        $this->teachers = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->min;
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

    public function getMin(): ?string
    {
        return $this->min;
    }

    public function setMin(string $min): static
    {
        $this->min = $min;

        return $this;
    }

    /**
     * @return Collection<int, Rate>
     */
    public function getRates(): Collection
    {
        return $this->rates;
    }

    public function addRate(Rate $rate): static
    {
        if (!$this->rates->contains($rate)) {
            $this->rates->add($rate);
            $rate->setCurrency($this);
        }

        return $this;
    }

    public function removeRate(Rate $rate): static
    {
        if ($this->rates->removeElement($rate)) {
            // set the owning side to null (unless already changed)
            if ($rate->getCurrency() === $this) {
                $rate->setCurrency(null);
            }
        }

        return $this;
    }

    public function getRate(): ?Rate
    {
        return $this->getRates()->count() > 0 ? $this->getRates()->last() : null;
    }

    /**
     * @return Collection<int, Teacher>
     */
    public function getTeachers(): Collection
    {
        return $this->teachers;
    }

    public function addTeacher(Teacher $teacher): static
    {
        if (!$this->teachers->contains($teacher)) {
            $this->teachers->add($teacher);
            $teacher->setCurrency($this);
        }

        return $this;
    }

    public function removeTeacher(Teacher $teacher): static
    {
        if ($this->teachers->removeElement($teacher)) {
            // set the owning side to null (unless already changed)
            if ($teacher->getCurrency() === $this) {
                $teacher->setCurrency(null);
            }
        }

        return $this;
    }
}
