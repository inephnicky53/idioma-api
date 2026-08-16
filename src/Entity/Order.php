<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
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
            security: 'is_granted("ROLE_USER")'
        )
    ],
    normalizationContext: ['groups' => ['order:list']]
)]
class Order
{
    use Datable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:list'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['order:list'])]
    private ?string $reference = null;

    /**
     * @var Collection<int, OrderProduct>
     */
    #[ORM\OneToMany(targetEntity: OrderProduct::class, mappedBy: 'command', orphanRemoval: true)]
    #[Groups(['order:list'])]
    private Collection $products;

    #[ORM\Column]
    #[Groups(['order:list', 'order:new'])]
    private ?float $amount = null;

    #[ORM\ManyToOne]
    #[Groups(['order:list'])]
    private ?Currency $currency = null;

    #[ORM\Column(length: 255)]
    #[Groups(['order:list'])]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    private ?User $user = null;

    #[ORM\OneToOne(inversedBy: 'command', cascade: ['persist', 'remove'])]
    #[Groups(['order:list'])]
    private ?Transaction $transaction = null;

    #[ORM\Column(length: 10)]
    #[Groups(['order:list'])]
    private ?string $operator = null;

    /**
     * @var Collection<int, UserCourse>
     */
    #[ORM\OneToMany(targetEntity: UserCourse::class, mappedBy: 'command')]
    private Collection $userCourses;

    public function __construct()
    {
        if (is_null($this->createdAt))
            $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->products = new ArrayCollection();
        $this->userCourses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * @return Collection<int, OrderProduct>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(OrderProduct $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setCommand($this);
        }

        return $this;
    }

    public function removeProduct(OrderProduct $product): static
    {
        if ($this->products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getCommand() === $this) {
                $product->setCommand(null);
            }
        }

        return $this;
    }

    public function getOperator(): ?string
    {
        return $this->operator;
    }

    public function setOperator(string $operator): static
    {
        $this->operator = $operator;

        return $this;
    }

    public function __toString(): string
    {
        return $this->reference ?? '';
    }
}
