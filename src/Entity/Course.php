<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Odm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\CourseRepository;
use App\Trait\Datable;
use App\Trait\Deletable;
use App\Trait\Ratingable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['course:list']]
        ),
        new Get(
            normalizationContext: ['groups' => ['course:list', 'course:view']]
        ),
        new Post(
            normalizationContext: ['groups' => ['course:list', 'course:view']],
            security: "is_granted('ROLE_USER')",
        )
    ]
)]
#[ApiFilter(OrderFilter::class, properties: ['id' => 'DESC'])]
class Course
{

    public const DIFFICULTY_EASY = "EAS";
    public const DIFFICULTY_NORMAL = "NOR";
    public const DIFFICULTY_HARD = "HAR";

    use Datable;
    use Deletable;
    use Ratingable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['course:list', 'user:courses', 'user:inbox:threads'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['course:list', 'user:courses', 'user:inbox:threads'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['course:list'])]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Groups(['course:list'])]
    private ?string $status = null;

    #[ORM\Column(length: 3)]
    #[Groups(['course:list'])]
    private ?string $difficulty = null;

    #[ORM\Column(length: 3)]
    #[Groups(['course:list'])]
    private ?string $level = null;

    #[ORM\Column]
    #[Groups(['course:list'])]
    private ?int $duration = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $state = null;

    #[ORM\Column]
    private ?bool $isPaid = true;

    #[ORM\ManyToOne(inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['course:list', 'user:courses'])]
    private ?Language $language = null;

    #[ORM\ManyToMany(targetEntity: Category::class)]
    #[Groups(['course:list'])]
    private Collection $categories;

    #[ORM\OneToMany(mappedBy: 'course', targetEntity: Reviews::class)]
    private Collection $reviews;

    #[ORM\OneToMany(mappedBy: 'course', targetEntity: Planning::class)]
    private Collection $plannings;

    #[ORM\ManyToOne(inversedBy: 'courses')]
    #[Groups(['course:list', 'user:courses'])]
    private ?Teacher $teacher = null;

    #[ORM\OneToMany(mappedBy: 'course', targetEntity: Attachment::class, cascade: ["persist"])]
    #[Groups(['course:list', 'user:courses'])]
    private Collection $thumbnails;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['course:list'])]
    private ?float $amount = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['course:list'])]
    private ?float $amountPromo = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['course:list'])]
    private ?bool $isPromote = null;


    #[ORM\OneToMany(mappedBy: 'course', targetEntity: UserCourse::class)]
    private Collection $studentCourses;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['course:list'])]
    private ?Currency $currency = null;

    #[ORM\OneToMany(mappedBy: 'course', targetEntity: Rating::class)]
    #[Groups(['course:list'])]
    private Collection $ratings;

    #[ORM\Column(length: 255)]
    private ?string $shortDescription = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['course:list'])]
    private ?bool $isBestseller = false;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['course:list'])]
    private ?bool $isNew = false;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['course:list'])]
    private ?bool $hasCertificate = true;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['course:list'])]
    private ?bool $hasLifetimeAccess = true;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['course:list'])]
    private ?int $quizzesCount = 0;

    #[ORM\OneToMany(mappedBy: 'course', targetEntity: CourseSection::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['course:list'])]
    private Collection $sections;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->plannings = new ArrayCollection();

        if (!$this->createdAt) $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->thumbnails = new ArrayCollection();
        $this->studentCourses = new ArrayCollection();
        $this->sections = new ArrayCollection();
    }

    public static function getDifficulties()
    {
        return [
            'easy' => self::DIFFICULTY_EASY,
            'normal' => self::DIFFICULTY_NORMAL,
            'hard' => self::DIFFICULTY_HARD,
        ];
    }

    public static function getDifficultiesBadge()
    {
        return [
            'success' => self::DIFFICULTY_EASY,
            'primary' => self::DIFFICULTY_NORMAL,
            'warning' => self::DIFFICULTY_HARD,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

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

    public function getDifficulty(): ?string
    {
        return $this->difficulty;
    }

    public function setDifficulty(string $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function isIsPaid(): ?bool
    {
        return $this->isPaid;
    }

    public function setIsPaid(bool $isPaid): static
    {
        $this->isPaid = $isPaid;

        return $this;
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
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    /**
     * @return Collection<int, Reviews>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Reviews $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setCourse($this);
        }

        return $this;
    }

    public function removeReview(Reviews $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getCourse() === $this) {
                $review->setCourse(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->title;
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
            $planning->setCourse($this);
        }

        return $this;
    }

    public function removePlanning(Planning $planning): static
    {
        if ($this->plannings->removeElement($planning)) {
            // set the owning side to null (unless already changed)
            if ($planning->getCourse() === $this) {
                $planning->setCourse(null);
            }
        }

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

    /**
     * @return Collection<int, Attachment>
     */
    public function getThumbnails(): Collection
    {
        return $this->thumbnails;
    }

    public function addThumbnail(Attachment $thumbnail): static
    {
        if (!$this->thumbnails->contains($thumbnail)) {
            $this->thumbnails->add($thumbnail);
            $thumbnail->setCourse($this);
        }

        return $this;
    }

    public function removeThumbnail(Attachment $thumbnail): static
    {
        if ($this->thumbnails->removeElement($thumbnail)) {
            // set the owning side to null (unless already changed)
            if ($thumbnail->getCourse() === $this) {
                $thumbnail->setCourse(null);
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

    public function getAmountPromo(): ?float
    {
        return $this->amountPromo;
    }

    public function setAmountPromo(?float $amountPromo): static
    {
        $this->amountPromo = $amountPromo;

        return $this;
    }

    public function isIsPromote(): ?bool
    {
        return $this->isPromote;
    }

    public function setIsPromote(bool $isPromote): static
    {
        $this->isPromote = $isPromote;

        return $this;
    }

    /**
     * @return Collection<int, UserCourse>
     */
    public function getStudentCourses(): Collection
    {
        return $this->studentCourses;
    }

    public function addStudentCourse(UserCourse $studentCourse): static
    {
        if (!$this->studentCourses->contains($studentCourse)) {
            $this->studentCourses->add($studentCourse);
            $studentCourse->setCourse($this);
        }

        return $this;
    }

    public function removeStudentCourse(UserCourse $studentCourse): static
    {
        if ($this->studentCourses->removeElement($studentCourse)) {
            // set the owning side to null (unless already changed)
            if ($studentCourse->getCourse() === $this) {
                $studentCourse->setCourse(null);
            }
        }

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

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function isIsBestseller(): ?bool
    {
        return $this->isBestseller;
    }

    public function setIsBestseller(bool $isBestseller): static
    {
        $this->isBestseller = $isBestseller;

        return $this;
    }

    public function isIsNew(): ?bool
    {
        return $this->isNew;
    }

    public function setIsNew(bool $isNew): static
    {
        $this->isNew = $isNew;

        return $this;
    }

    public function isHasCertificate(): ?bool
    {
        return $this->hasCertificate;
    }

    public function setHasCertificate(bool $hasCertificate): static
    {
        $this->hasCertificate = $hasCertificate;

        return $this;
    }

    public function isHasLifetimeAccess(): ?bool
    {
        return $this->hasLifetimeAccess;
    }

    public function setHasLifetimeAccess(bool $hasLifetimeAccess): static
    {
        $this->hasLifetimeAccess = $hasLifetimeAccess;

        return $this;
    }

    public function getQuizzesCount(): ?int
    {
        return $this->quizzesCount;
    }

    public function setQuizzesCount(int $quizzesCount): static
    {
        $this->quizzesCount = $quizzesCount;

        return $this;
    }

    /**
     * @return Collection<int, CourseSection>
     */
    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(CourseSection $section): static
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setCourse($this);
        }

        return $this;
    }

    public function removeSection(CourseSection $section): static
    {
        if ($this->sections->removeElement($section)) {
            if ($section->getCourse() === $this) {
                $section->setCourse(null);
            }
        }

        return $this;
    }

    #[Groups(['course:list'])]
    public function getEnrolledCount(): int
    {
        return $this->studentCourses->count();
    }

    #[Groups(['course:list'])]
    public function getLessonsCount(): int
    {
        $count = 0;
        foreach ($this->sections as $section) {
            $count += $section->getLessons()->count();
        }
        return $count;
    }

    /**
     * Total duration in minutes: sum of lesson durations if a curriculum
     * exists, otherwise falls back to the manually set `duration` field.
     */
    #[Groups(['course:list'])]
    public function getTotalDurationMinutes(): int
    {
        $sum = 0;
        foreach ($this->sections as $section) {
            foreach ($section->getLessons() as $lesson) {
                $sum += $lesson->getDurationMinutes() ?? 0;
            }
        }
        return $sum > 0 ? $sum : (int) $this->duration;
    }

}
