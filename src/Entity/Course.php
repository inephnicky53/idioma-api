<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Contract\PayableInterface;
use App\Enum\Currency;
use App\Enum\PurchaseType;
use App\Model\UploadedFileAwareInterface;
use App\Repository\CourseRepository;
use App\State\Provider\CourseCollectionProvider;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(provider: CourseCollectionProvider::class),
        new Get(),
    ],
    normalizationContext: ['groups' => ['course:read']],
)]
class Course implements PayableInterface, UploadedFileAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['course:read', 'payment:read', 'course_purchase:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est requis')]
    #[Groups(['course:read', 'payment:read', 'course_purchase:read'])]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['course:read', 'course_purchase:read'])]
    private ?string $titleEn = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['course:read', 'course_purchase:read'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['course:read', 'course_purchase:read'])]
    private ?string $descriptionEn = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix est requis')]
    #[Assert\Positive(message: 'Le prix doit être positif')]
    #[Groups(['course:read'])]
    private ?string $price = null;

    #[ORM\Column(length: 3, enumType: Currency::class)]
    #[Groups(['course:read'])]
    private Currency $currency = Currency::USD;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['course:read', 'course_purchase:read'])]
    private ?string $thumbnail = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['course:read', 'course_purchase:read'])]
    private ?string $ebookPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['course:read', 'course_purchase:read'])]
    private ?string $ebookTitle = null;

    #[ORM\Column]
    #[Groups(['course:read'])]
    private bool $isPublished = false;

    #[ORM\Column]
    #[Groups(['course:read'])]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['course:read'])]
    private ?DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $updatedAt = null;

    /** @var Collection<int, CourseVideo> */
    #[ORM\OneToMany(targetEntity: CourseVideo::class, mappedBy: 'course', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['course:read', 'course_purchase:read'])]
    private Collection $videos;

    /** @var Collection<int, CoursePurchase> */
    #[ORM\OneToMany(targetEntity: CoursePurchase::class, mappedBy: 'course')]
    private Collection $purchases;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->videos = new ArrayCollection();
        $this->purchases = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title ?? 'Nouveau cours';
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

    public function getTitleEn(): ?string
    {
        return $this->titleEn;
    }

    public function setTitleEn(?string $titleEn): static
    {
        $this->titleEn = $titleEn;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDescriptionEn(): ?string
    {
        return $this->descriptionEn;
    }

    public function setDescriptionEn(?string $descriptionEn): static
    {
        $this->descriptionEn = $descriptionEn;
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price ?? '0.00';
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    public function getEbookPath(): ?string
    {
        return $this->ebookPath;
    }

    public function setEbookPath(?string $ebookPath): static
    {
        $this->ebookPath = $ebookPath;
        return $this;
    }

    public function getEbookTitle(): ?string
    {
        return $this->ebookTitle;
    }

    public function setEbookTitle(?string $ebookTitle): static
    {
        $this->ebookTitle = $ebookTitle;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
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

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTime();
    }

    /** @return Collection<int, CourseVideo> */
    public function getVideos(): Collection
    {
        return $this->videos;
    }

    public function addVideo(CourseVideo $video): static
    {
        if (!$this->videos->contains($video)) {
            $this->videos->add($video);
            $video->setCourse($this);
        }
        return $this;
    }

    public function removeVideo(CourseVideo $video): static
    {
        if ($this->videos->removeElement($video)) {
            if ($video->getCourse() === $this) {
                $video->setCourse(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, CoursePurchase> */
    public function getPurchases(): Collection
    {
        return $this->purchases;
    }

    #[Groups(['course:read', 'course_purchase:read'])]
    public function getVideoCount(): int
    {
        return $this->videos->count();
    }

    #[Groups(['course:read', 'course_purchase:read'])]
    public function getTotalDuration(): int
    {
        $total = 0;
        foreach ($this->videos as $video) {
            $total += $video->getDuration() ?? 0;
        }
        return $total;
    }

    #[Groups(['course:read'])]
    public function getFormattedPrice(): string
    {
        return number_format((float) $this->price, 2) . ' ' . $this->currency->value;
    }

    // ========== UploadedFileAwareInterface Implementation ==========

    public function getFilePropertyMapping(): array
    {
        return [
            'thumbnail' => 'thumbnail',
            'ebookPath' => 'ebookPath',
        ];
    }

    // ========== PayableInterface Implementation ==========

    public function getLabel(): string
    {
        return $this->title ?? 'Cours';
    }

    public function getPurchaseType(): PurchaseType
    {
        return PurchaseType::COURSE;
    }

    public function isAvailableForPurchase(): bool
    {
        return $this->isPublished;
    }
}
