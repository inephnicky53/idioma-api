<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\ApiRegisterController;
use App\Controller\Api\User\ApiPhoneVerifyController;
use App\Controller\Api\User\ApiUserCoursesController;
use App\Dto\ResetPasswordInput;
use App\Dto\ResetRequestedInput;
use App\Dto\VerifyOTPInput;
use App\Repository\UserRepository;
use App\Service\GeoIP;
use App\State\User\OTPVerificationProcessor;
use App\State\User\ResetPasswordProcessor;
use App\State\User\ResetRequestedProcessor;
use App\State\User\UserMeProvider;
use App\State\User\UserPasswordHasher;
use App\Trait\Datable;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use GeoIp2\Exception\AddressNotFoundException;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use function Symfony\Component\String\u;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: "Cet email {{ value }} est déjà utilisé par un autre compte")]
#[UniqueEntity(fields: ['phone'], message: "Ce numéro {{ value }} est déjà utilisé pour un autre compte")]
#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: 'user/courses',
        controller: ApiUserCoursesController::class,
        normalizationContext: ['groups' => ['user:courses']],
        security: "is_granted('ROLE_USER')",
        read: false
    ),
    new Get(
        normalizationContext: ['groups' => ['user:show']],
    ),
    new Get(
        uriTemplate: "user/me",
        normalizationContext: ['groups' => ['user:me', 'user:show']],
        security: "is_granted('ROLE_USER')",
        provider: UserMeProvider::class,
    ),
    new Get(
        uriTemplate: 'user/phone/verify',
        controller: ApiPhoneVerifyController::class,
        normalizationContext: ['groups' => ['user:phone:verify']],
        security: "is_granted('ROLE_USER')",
        read: false
    ),
    new Post(
        uriTemplate: 'users/otp/verification',
        input: VerifyOTPInput::class,
        processor: OTPVerificationProcessor::class,
    ),
    new Post(
        uriTemplate: '/register',
        controller: ApiRegisterController::class,
        denormalizationContext: ['groups' => ['user:register']],
        write: false
    ),
    new Post(
        uriTemplate: '/users/reset-requested',
        input: ResetRequestedInput::class,
        processor: ResetRequestedProcessor::class,
    ),
    new Post(
        uriTemplate: '/users/reset-password',
        input: ResetPasswordInput::class,
        processor: ResetPasswordProcessor::class
    ),
    new Patch(
        uriTemplate: '/users/{id}',
        normalizationContext: ['groups' => ['user:me']],
        denormalizationContext: ['groups' => ['user:update']],
        security: "is_granted('ROLE_USER')",
    ),
    new Patch(
        normalizationContext: ['groups' => ['user:me']],
        denormalizationContext: ['groups' => ['user:update']],
        security: "is_granted('ROLE_ADMIN') or is_granted('USER_EDIT', object)",
        securityMessage: "Désolé, vous n'avez pas le droit de modifier cet utilisateur.",
        processor: UserPasswordHasher::class
    ),
])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use Datable {
        Datable::__construct as private dateConstructor;
    }

    const USER = "user.user";
    const STUDENT = "user.student";
    const TEACHER = "user.teacher";
    const ADMIN = "user.admin";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:show', 'teacher:list', 'user:courses', 'user:teachers', 'user:inbox', 'planning:show', 'rating:list'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:show', 'user:register', 'course:list', 'user:inbox', 'planning:show'])]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(['user:show', 'user:register'])]
    private array $roles = [];

    #[ORM\Column]
    #[Groups(['user:register'])]
    private ?string $password = null;

    #[Groups(['user:update', 'user:reset-password'])]
    private ?string $plainPassword = null;

    #[ORM\Column(length: 3, nullable: true, options: ["default" => "CD"])]
    #[Groups(['user:show', 'user:update', 'user:register', 'teacher:list', 'user:inbox', 'planning:show'])]
    private ?string $country;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastLoginIp = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeInterface $bannedAt = null;

    #[ORM\Column(nullable: true)]
    private ?string $confirmationToken = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['user:show'])]
    private bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:register', 'planning:show', 'user:update', 'user:inbox'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['user:register', 'planning:show', 'user:update', 'user:inbox'])]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:register', 'planning:show', 'user:update', 'user:inbox'])]
    private ?string $postname = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $birthdayAt;

    #[ORM\Column(length: 15, unique: true, nullable: true)]
    #[Groups(['user:register', 'planning:show', 'user:inbox'])]
    private ?string $phone = null;

    #[ORM\Column]
    #[Groups(['user:show'])]
    private ?bool $isActive = true;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: OTP::class, orphanRemoval: true)]
    private Collection $OTPs;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $timezone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $postal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subdivisions = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $isp = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastLogin = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Teacher $teacher = null;

    #[ORM\Column]
    #[Groups(['user:phone:verify'])]
    private ?bool $isPhoneVerified;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Attachment::class, cascade: ["persist"])]
    #[Groups(['course:list', 'user:courses', 'teacher:list', 'user:inbox'])]
    private Collection $thumbnails;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserCourse::class, orphanRemoval: true)]
    #[Groups(['course:list'])]
    private Collection $courses;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Transaction::class)]
    private Collection $transactions;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Order::class)]
    private Collection $orders;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Rating::class, orphanRemoval: true)]
    private Collection $ratings;

    #[ORM\Column(length: 5, options: ["default" => "fr"])]
    #[Groups(['user:me', 'user:me:language'])]
    private ?string $language;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserTeacher::class, orphanRemoval: true)]
    #[Groups(['user:teachers'])]
    private Collection $teachers;

    #[ORM\Column(length: 3, options: ["default" => "USD"])]
    #[Groups(['user:me', 'user:me:currency'])]
    private ?string $currency;

    #[ORM\ManyToMany(targetEntity: Language::class)]
    private Collection $goals;

    #[ORM\ManyToMany(targetEntity: Planning::class, mappedBy: 'participants')]
    private Collection $plannings;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: InboxMessage::class)]
    private Collection $inboxMessages;

    #[ORM\ManyToMany(targetEntity: InboxThread::class, mappedBy: 'participants')]
    #[Groups(['user:inbox:threads'])]
    private Collection $inboxThreads;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:show', 'user:me', 'user:update'])]
    private ?string $profile = null;

    public static function getRolesList(): array
    {
        return array(
            self::USER => 'ROLE_USER',
            self::STUDENT => 'ROLE_STUDENT',
            self::TEACHER => 'ROLE_TEACHER',
            self::ADMIN => 'ROLE_ADMIN',
        );
    }

    /**
     * @throws \Exception
     */
    public function __construct($ip = null)
    {
        $this->dateConstructor();
        $this->isPhoneVerified = false;
        $this->OTPs = new ArrayCollection();

        $this->country = 'CD';
        $this->language = 'fr';
        $this->currency = 'USD';
        $this->birthdayAt = new DateTime("01-01-2000");

        //if ($ip) $this->initGeoIp($ip);
        $this->thumbnails = new ArrayCollection();
        $this->courses = new ArrayCollection();
        $this->transactions = new ArrayCollection();
        $this->orders = new ArrayCollection();
        $this->ratings = new ArrayCollection();
        $this->teachers = new ArrayCollection();
        $this->goals = new ArrayCollection();
        $this->plannings = new ArrayCollection();
        $this->inboxMessages = new ArrayCollection();
        $this->inboxThreads = new ArrayCollection();
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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string)$this->email;
    }

    #[Groups(['teacher:list', 'course:list', 'user:teachers:fullname', 'user:inbox', 'rating:list'])]
    public function getFullname(): ?string
    {
        $fullname = $this->firstname;
        if ($this->name)
            $fullname .= " {$this->name}";
        return $fullname;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->lastLoginIp;
    }

    public function setLastLoginIp(?string $lastLoginIp): User
    {
        $this->lastLoginIp = $lastLoginIp;

        return $this;
    }

    public function getLastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(DateTimeImmutable $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function isBanned(): bool
    {
        return null !== $this->bannedAt;
    }

    public function getBannedAt(): ?DateTimeInterface
    {
        return $this->bannedAt;
    }

    public function setBannedAt(?DateTimeInterface $bannedAt): User
    {
        $this->bannedAt = $bannedAt;

        return $this;
    }

    public function getConfirmationToken(): ?string
    {
        return $this->confirmationToken;
    }

    public function setConfirmationToken(?string $confirmationToken): User
    {
        $this->confirmationToken = $confirmationToken;

        return $this;
    }

    public function canLogin(): bool
    {
        return !$this->isBanned() && null === $this->getConfirmationToken();
    }

    public function __toString(): string
    {
        return $this->getFullname();
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getPostname(): ?string
    {
        return $this->postname;
    }

    public function setPostname(?string $postname): static
    {
        $this->postname = $postname;

        return $this;
    }

    public function getBirthdayAt(): ?\DateTime
    {
        return $this->birthdayAt;
    }

    public function setBirthdayAt(?\DateTime $birthdayAt): static
    {
        $this->birthdayAt = $birthdayAt;

        return $this;
    }

    public function getPhone($withPlus = false): ?string
    {
        if ($withPlus)
            return '+' . $this->phone;

        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $prefix = GeoIP::countryPrefix($this->getCountry());

        if ($phone) {
            $phone = u($phone)
                ->replace(' ', '')
                ->replace('(', '')
                ->replace(')', '');

            if ($phone->startsWith($prefix)) {
                $this->phone = $phone;
            } elseif ($phone->startsWith("+$prefix")) {
                $this->phone = $phone->splice('', 0, 1);
            } elseif ($phone->startsWith("00$prefix")) {
                $this->phone = $phone->splice('', 0, 2);
            } elseif ($phone->startsWith("0")) {
                $this->phone = $phone->splice($prefix, 0, 1);
            } elseif ($phone->startsWith("+")) {
                $this->phone = $phone->splice('', 0, 1);
            } else
                $this->phone = $phone->prepend($prefix);
        }
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

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getPostal(): ?string
    {
        return $this->postal;
    }

    public function setPostal(?string $postal): self
    {
        $this->postal = $postal;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getSubdivisions(): ?string
    {
        return $this->subdivisions;
    }

    public function setSubdivisions(?string $subdivisions): self
    {
        $this->subdivisions = $subdivisions;

        return $this;
    }

    public function getIsp(): ?string
    {
        return $this->isp;
    }

    public function setIsp(?string $isp): self
    {
        $this->isp = $isp;

        return $this;
    }

    public function getLastLogin(): ?DateTimeImmutable
    {
        return $this->lastLogin;
    }

    public function setLastLogin(DateTimeImmutable $lastLogin): self
    {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string $ip
     * @throws \Exception
     */
    public function initGeoIp(string $ip)
    {
        try {
            // if you are in the production environment you can retrieve the
            $record = GeoIP::check($ip);

            $this->setIsp($record->isp ?? null);
            $this->setSubdivisions($record->regionName ?? null);
            $this->setCity($record->city ?? null); // 'Minneapolis'
            $this->setPostal($record->zip ?? null); // '55455'
            $this->setIp($record->query ?? null);
            $this->setTimezone($record->timezone ?? null);
            $this->setLatitude($record->lat ?? null); // 44.9733
            $this->setLongitude($record->lon ?? null);
        } catch (AddressNotFoundException $ex) {
            throw new \Exception("It wasn't possible to retrieve information about the providen IP");
        }
    }

    public function getTeacher(): ?Teacher
    {
        return $this->teacher;
    }

    public function setTeacher(?Teacher $teacher): static
    {
        // unset the owning side of the relation if necessary
        if ($teacher === null && $this->teacher !== null) {
            $this->teacher->setUser(null);
        }

        // set the owning side of the relation if necessary
        if ($teacher !== null && $teacher->getUser() !== $this) {
            $teacher->setUser($this);
        }

        $this->teacher = $teacher;

        return $this;
    }

    public function isIsPhoneVerified(): ?bool
    {
        return $this->isPhoneVerified;
    }

    public function setIsPhoneVerified(bool $isPhoneVerified): static
    {
        $this->isPhoneVerified = $isPhoneVerified;

        return $this;
    }

    /**
     * @return Collection<int, Attachment>
     */
    public function getThumbnails(): Collection
    {
        return $this->thumbnails;
    }

    public function getThumbnail(): ?Attachment
    {
        return count($this->thumbnails) > 0 ? $this->thumbnails->first() : null;
    }

    public function addThumbnail(Attachment $thumbnail): static
    {
        if (!$this->thumbnails->contains($thumbnail)) {
            $this->thumbnails->add($thumbnail);
            $thumbnail->setUser($this);
        }

        return $this;
    }

    public function removeThumbnail(Attachment $thumbnail): static
    {
        if ($this->thumbnails->removeElement($thumbnail)) {
            // set the owning side to null (unless already changed)
            if ($thumbnail->getUser() === $this) {
                $thumbnail->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserCourse>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(UserCourse $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
            $course->setUser($this);
        }

        return $this;
    }

    public function removeCourse(UserCourse $course): static
    {
        if ($this->courses->removeElement($course)) {
            // set the owning side to null (unless already changed)
            if ($course->getUser() === $this) {
                $course->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setUser($this);
        }

        return $this;
    }

    public function removeTransaction(Transaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction)) {
            // set the owning side to null (unless already changed)
            if ($transaction->getUser() === $this) {
                $transaction->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setUser($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getUser() === $this) {
                $order->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Rating>
     */
    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    public function addRating(Rating $rating): static
    {
        if (!$this->ratings->contains($rating)) {
            $this->ratings->add($rating);
            $rating->setUser($this);
        }

        return $this;
    }

    public function removeRating(Rating $rating): static
    {
        if ($this->ratings->removeElement($rating)) {
            // set the owning side to null (unless already changed)
            if ($rating->getUser() === $this) {
                $rating->setUser(null);
            }
        }

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    /**
     * @return Collection<int, UserTeacher>
     */
    public function getTeachers(): Collection
    {
        return $this->teachers;
    }

    public function addTeacher(UserTeacher $teacher): static
    {
        if (!$this->teachers->contains($teacher)) {
            $this->teachers->add($teacher);
            $teacher->setUser($this);
        }

        return $this;
    }

    public function removeTeacher(UserTeacher $teacher): static
    {
        if ($this->teachers->removeElement($teacher)) {
            // set the owning side to null (unless already changed)
            if ($teacher->getUser() === $this) {
                $teacher->setUser(null);
            }
        }

        return $this;
    }


    #[Groups(['user:me'])]
    public function getHours(): float
    {
        $hours = 0;
        $this->teachers->map(function (UserTeacher $teacher) use (&$hours) {
            $hours += $teacher->getHours();
        });
        return $hours;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return Collection<int, Language>
     */
    public function getGoals(): Collection
    {
        return $this->goals;
    }

    public function addGoal(Language $goal): static
    {
        if (!$this->goals->contains($goal)) {
            $this->goals->add($goal);
        }

        return $this;
    }

    public function removeGoal(Language $goal): static
    {
        $this->goals->removeElement($goal);

        return $this;
    }

    /**
     * @return Collection<int, Planning>
     */
    public function getPlannings(): Collection
    {
        return $this->plannings;
    }

    public function addPlanning(Planning $planning): static
    {
        if (!$this->plannings->contains($planning)) {
            $this->plannings->add($planning);
            $planning->addParticipant($this);
        }

        return $this;
    }

    public function removePlanning(Planning $planning): static
    {
        if ($this->plannings->removeElement($planning)) {
            $planning->removeParticipant($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, InboxMessage>
     */
    public function getInboxMessages(): Collection
    {
        return $this->inboxMessages;
    }

    public function addInboxMessage(InboxMessage $inboxMessage): static
    {
        if (!$this->inboxMessages->contains($inboxMessage)) {
            $this->inboxMessages->add($inboxMessage);
            $inboxMessage->setAuthor($this);
        }

        return $this;
    }

    public function removeInboxMessage(InboxMessage $inboxMessage): static
    {
        if ($this->inboxMessages->removeElement($inboxMessage)) {
            // set the owning side to null (unless already changed)
            if ($inboxMessage->getAuthor() === $this) {
                $inboxMessage->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, InboxThread>
     */
    public function getInboxThreads(): Collection
    {
        return $this->inboxThreads;
    }

    public function addInboxThread(InboxThread $inboxThread): static
    {
        if (!$this->inboxThreads->contains($inboxThread)) {
            $this->inboxThreads->add($inboxThread);
            $inboxThread->addParticipant($this);
        }

        return $this;
    }

    public function removeInboxThread(InboxThread $inboxThread): static
    {
        if ($this->inboxThreads->removeElement($inboxThread)) {
            $inboxThread->removeParticipant($this);
        }

        return $this;
    }

    public function getProfile(): ?string
    {
        return $this->profile;
    }

    public function setProfile(?string $profile): static
    {
        $this->profile = $profile;

        return $this;
    }
}
