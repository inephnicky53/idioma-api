<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\TransactionRepository;
use App\State\Transaction\CheckTransactionProvider;
use App\Trait\Datable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['transaction:list']],
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            uriTemplate: 'transactions/{id}/check',
            provider: CheckTransactionProvider::class,
        )
    ]
)]
class Transaction
{
    use Datable {
        Datable::__construct as private dateConstructor;
    }

    const OPERATOR_BANK = 'PAYIN-BNK';
    const OPERATOR_MOBILE = 'PAYIN-MOB';
    const OPERATOR_PAYPAL = 'PAYPAL';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:list', 'transaction:list'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['order:list', 'transaction:list'])]
    private ?\DateTimeImmutable $respondedAt = null;

    #[ORM\Column(length: 15, nullable: true)]
    #[Groups(['transaction:new', 'transaction:list'])]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    #[Groups(['order:list', 'transaction:list'])]
    private ?string $status = null;

    #[ORM\Column(length: 255)]
    #[Groups(['order:list', 'transaction:list'])]
    private ?string $operator = null;

    #[ORM\Column]
    #[Groups(['order:list', 'transaction:list'])]
    private ?float $amount = null;

    #[ORM\Column(length: 255)]
    #[Groups(['order:list', 'transaction:list'])]
    private ?string $reference = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerReference = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['order:list', 'transaction:list'])]
    private ?string $message = null;

    #[ORM\ManyToOne]
    #[Groups(['transaction:list'])]
    private ?Currency $currency = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['transaction:list'])]
    private ?User $user = null;

    #[ORM\OneToOne(mappedBy: 'transaction', cascade: ['persist', 'remove'])]
    #[Groups(['transaction:list'])]
    private ?Order $command = null;

    /**
     * @var Collection<int, Fee>
     */
    #[ORM\ManyToMany(targetEntity: Fee::class)]
    private Collection $fees;

    #[ORM\Column(nullable: true)]
    private ?float $fee = 0;

    public function __construct()
    {
        $this->dateConstructor();
        if (!($this->reference))
            $this->reference = uniqid();
        $this->fees = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRespondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function setRespondedAt(?\DateTimeImmutable $respondedAt): static
    {
        $this->respondedAt = $respondedAt;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

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

    public function getOperator(): ?string
    {
        return $this->operator;
    }

    public static function getOperatorList(): array
    {
        return [
            self::OPERATOR_BANK,
            self::OPERATOR_MOBILE,
            self::OPERATOR_PAYPAL,
        ];
    }

    public function setOperator(string $operator): static
    {
        $this->operator = $operator;

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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getProviderReference(): ?string
    {
        return $this->providerReference;
    }

    public function setProviderReference(?string $providerReference): static
    {
        $this->providerReference = $providerReference;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCommand(): ?Order
    {
        return $this->command;
    }

    public function setCommand(?Order $command): static
    {
        // unset the owning side of the relation if necessary
        if ($command === null && $this->command !== null) {
            $this->command->setTransaction(null);
        }

        // set the owning side of the relation if necessary
        if ($command !== null && $command->getTransaction() !== $this) {
            $command->setTransaction($this);
        }

        $this->command = $command;

        return $this;
    }

    /**
     * @return Collection<int, Fee>
     */
    public function getFees(): Collection
    {
        return $this->fees;
    }

    public function addFee(Fee $fee): static
    {
        if (!$this->fees->contains($fee)) {
            $this->fees->add($fee);
        }

        return $this;
    }

    public function removeFee(Fee $fee): static
    {
        $this->fees->removeElement($fee);

        return $this;
    }

    public function getFee(): ?float
    {
        return $this->fee;
    }

    public function setFee(?float $fee): static
    {
        $this->fee = $fee;

        return $this;
    }
}
