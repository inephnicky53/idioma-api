<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\OrderRepository;
use App\Trait\Datable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['order:list']],
        ),
    ]
)]
class Order
{
    use Datable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:list'])]
    private ?int $id = null;

    #[ORM\OneToMany(mappedBy: 'command', targetEntity: UserCourse::class, cascade: ["persist"])]
    private Collection $userCourses;

    #[ORM\Column]
    #[Groups(['order:list', 'order:new'])]
    private ?float $amount = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    private ?User $user = null;

    #[ORM\OneToOne(inversedBy: 'command', cascade: ['persist', 'remove'])]
    private ?Transaction $transaction = null;

    #[ORM\ManyToOne]
    private ?Currency $currency = null;

    #[ORM\OneToMany(mappedBy: 'command', targetEntity: OrderTeacher::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $orderTeachers;

    public function __construct()
    {
        if (is_null($this->createdAt))
            $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->userCourses = new ArrayCollection();
        $this->orderTeachers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, UserCourse>
     */
    public function getUserCourses(): Collection
    {
        return $this->userCourses;
    }

    public function addUserCourse(UserCourse $userCourse): static
    {
        if (!$this->userCourses->contains($userCourse)) {
            $this->userCourses->add($userCourse);
            $userCourse->setCommand($this);
        }

        return $this;
    }

    public function removeUserCourse(UserCourse $userCourse): static
    {
        if ($this->userCourses->removeElement($userCourse)) {
            // set the owning side to null (unless already changed)
            if ($userCourse->getCommand() === $this) {
                $userCourse->setCommand(null);
            }
        }

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
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

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): static
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return Collection<int, OrderTeacher>
     */
    public function getOrderTeachers(): Collection
    {
        return $this->orderTeachers;
    }

    public function addOrderTeacher(OrderTeacher $orderTeacher): static
    {
        if (!$this->orderTeachers->contains($orderTeacher)) {
            $this->orderTeachers->add($orderTeacher);
            $orderTeacher->setCommand($this);
        }

        return $this;
    }

    public function removeOrderTeacher(OrderTeacher $orderTeacher): static
    {
        if ($this->orderTeachers->removeElement($orderTeacher)) {
            // set the owning side to null (unless already changed)
            if ($orderTeacher->getCommand() === $this) {
                $orderTeacher->setCommand(null);
            }
        }

        return $this;
    }
}
