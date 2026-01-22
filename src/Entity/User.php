<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\RegisterDto;
use App\Enum\Currency;
use App\Enum\PaymentMethod;
use App\Repository\UserRepository;
use App\State\Processor\UserRegisterProcessor;
use App\State\Provider\RegisterDtoProvider;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: 'is_granted("ROLE_ADMIN")',
            securityMessage: 'Only admins can list users'
        ),
        new Get(
            security: 'is_granted("ROLE_USER") and object == user or is_granted("ROLE_ADMIN")',
            securityMessage: 'Users can only view their own profile'
        ),
        new Post(
            uriTemplate: '/auth/register',
            description: 'Inscription - Crée un utilisateur avec ou sans paiement',
            normalizationContext: ['groups' => ['user:read']],
            input: RegisterDto::class,
            validate: false,
            name: 'register',
            provider: RegisterDtoProvider::class,
            processor: UserRegisterProcessor::class
        ),
        new Patch(
            normalizationContext: ['groups' => ['user:read']],
            denormalizationContext: ['groups' => ['user:write']],
            security: 'is_granted("ROLE_USER") and object == user or is_granted("ROLE_ADMIN")',
            securityMessage: 'Users can only update their own profile'
        ),
        new Delete(
            security: 'is_granted("ROLE_ADMIN")',
            securityMessage: 'Only admins can delete users'
        )
    ],
    normalizationContext: ['groups' => ['user:read']],
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read', 'subscription:read', 'payment:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'Email cannot be empty')]
    #[Assert\Email(message: 'The email "{{ value }}" is not valid')]
    #[Groups(['user:read', 'user:write', 'subscription:read', 'payment:read'])]
    private ?string $email = null;

    #[ORM\Column(type: 'json')]
    private array $roles;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Password cannot be empty')]
    #[Groups(['user:write'])]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'First name cannot be empty')]
    #[Groups(['user:read', 'user:write', 'subscription:read'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Last name cannot be empty')]
    #[Groups(['user:read', 'user:write', 'subscription:read'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, unique: true, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['user:read'])]
    private ?DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['user:read'])]
    private ?DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastLoginAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resetPasswordToken = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $resetPasswordTokenExpiresAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $refreshToken = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $refreshTokenExpiresAt = null;

    /**
     * JWT Token - Not persisted in database, only for API responses
     */
    #[Groups(['user:read'])]
    private ?string $jwtToken = null;

    /**
     * @var Collection<int, Subscription>
     */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'user', cascade: ['remove'])]
    #[Groups(['user:read'])]
    private Collection $subscriptions;

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $payments;

    /**
     * @var Collection<int, CheckIn>
     */
    #[ORM\OneToMany(targetEntity: CheckIn::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $checkIns;

    /**
     * @var Collection<int, TimeSlot>
     */
    #[ORM\ManyToMany(targetEntity: TimeSlot::class, mappedBy: 'users')]
    #[Groups(['user:read'])]
    private Collection $timeSlots;

    // Champs spécifiques au club
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $level = null; // beginner, intermediate, advanced

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?array $schedules = null; // Array of schedule IDs

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $participationType = null; // presential, online, mixed

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read', 'user:write'])]
    private bool $isClubMember = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $learningGoals = null;

    // Champs temporaires pour l'inscription avec paiement (non persistés)
    private ?int $subscriptionPlanId = null;
    private ?PaymentMethod $paymentMethod = null;
    private ?Currency $currency = null;
    private ?string $token = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->subscriptions = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->checkIns = new ArrayCollection();
        $this->timeSlots = new ArrayCollection();
        $this->roles = ['ROLE_USER'];
    }

    public function __toString(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        if (empty($roles)) {
            $roles[] = 'ROLE_USER';
        }
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
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

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getLastLoginAt(): ?DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?DateTimeInterface $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getResetPasswordToken(): ?string
    {
        return $this->resetPasswordToken;
    }

    public function setResetPasswordToken(?string $resetPasswordToken): static
    {
        $this->resetPasswordToken = $resetPasswordToken;
        return $this;
    }

    public function getResetPasswordTokenExpiresAt(): ?DateTimeInterface
    {
        return $this->resetPasswordTokenExpiresAt;
    }

    public function setResetPasswordTokenExpiresAt(?DateTimeInterface $resetPasswordTokenExpiresAt): static
    {
        $this->resetPasswordTokenExpiresAt = $resetPasswordTokenExpiresAt;
        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    public function getRefreshTokenExpiresAt(): ?DateTimeInterface
    {
        return $this->refreshTokenExpiresAt;
    }

    public function setRefreshTokenExpiresAt(?DateTimeInterface $refreshTokenExpiresAt): static
    {
        $this->refreshTokenExpiresAt = $refreshTokenExpiresAt;
        return $this;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): static
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setUser($this);
        }
        return $this;
    }

    public function removeSubscription(Subscription $subscription): static
    {
        if ($this->subscriptions->removeElement($subscription)) {
            if ($subscription->getUser() === $this) {
                $subscription->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setUser($this);
        }
        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getUser() === $this) {
                $payment->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, CheckIn>
     */
    public function getCheckIns(): Collection
    {
        return $this->checkIns;
    }

    public function addCheckIn(CheckIn $checkIn): static
    {
        if (!$this->checkIns->contains($checkIn)) {
            $this->checkIns->add($checkIn);
            $checkIn->setUser($this);
        }
        return $this;
    }

    public function removeCheckIn(CheckIn $checkIn): static
    {
        if ($this->checkIns->removeElement($checkIn)) {
            if ($checkIn->getUser() === $this) {
                $checkIn->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, TimeSlot>
     */
    public function getTimeSlots(): Collection
    {
        return $this->timeSlots;
    }

    public function addTimeSlot(TimeSlot $timeSlot): static
    {
        if (!$this->timeSlots->contains($timeSlot)) {
            $this->timeSlots->add($timeSlot);
            $timeSlot->addUser($this);
        }
        return $this;
    }

    public function removeTimeSlot(TimeSlot $timeSlot): static
    {
        if ($this->timeSlots->removeElement($timeSlot)) {
            $timeSlot->removeUser($this);
        }
        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getSchedules(): ?array
    {
        return $this->schedules;
    }

    public function setSchedules(?array $schedules): static
    {
        $this->schedules = $schedules;
        return $this;
    }

    public function getParticipationType(): ?string
    {
        return $this->participationType;
    }

    public function setParticipationType(?string $participationType): static
    {
        $this->participationType = $participationType;
        return $this;
    }

    public function isClubMember(): bool
    {
        return $this->isClubMember;
    }

    public function setIsClubMember(bool $isClubMember): static
    {
        $this->isClubMember = $isClubMember;
        return $this;
    }

    public function getLearningGoals(): ?string
    {
        return $this->learningGoals;
    }

    public function setLearningGoals(?string $learningGoals): static
    {
        $this->learningGoals = $learningGoals;
        return $this;
    }

    // Getters et setters pour les champs temporaires d'inscription avec paiement
    public function getSubscriptionPlanId(): ?int
    {
        return $this->subscriptionPlanId;
    }

    public function setSubscriptionPlanId(?int $subscriptionPlanId): static
    {
        $this->subscriptionPlanId = $subscriptionPlanId;
        return $this;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PaymentMethod $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
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

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function getJwtToken(): ?string
    {
        return $this->jwtToken;
    }

    public function setJwtToken(?string $jwtToken): static
    {
        $this->jwtToken = $jwtToken;
        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTime();
    }
}

