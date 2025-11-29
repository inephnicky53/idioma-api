<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Controller\Api\VideoStreamAction;
use App\Repository\CourseVideoRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CourseVideoRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Get(
            uriTemplate: '/course_videos/{id}/stream',
            controller: VideoStreamAction::class,
            security: "is_granted('ROLE_USER')",
            name: 'stream',
            read: false,
            serialize: false,
            description: 'Stream une vidéo de cours avec support du Range Request'
        ),
    ],
    normalizationContext: ['groups' => ['course_video:read']],
)]
class CourseVideo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['course_video:read', 'course:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'videos')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['course_video:read'])]
    private ?Course $course = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est requis')]
    #[Groups(['course_video:read', 'course:read'])]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['course_video:read', 'course:read'])]
    private ?string $titleEn = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['course_video:read'])]
    private ?string $description = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank(message: 'Le fichier vidéo est requis')]
    #[Groups(['course_video:read'])]
    private ?string $videoFile = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['course_video:read', 'course:read'])]
    private ?int $duration = null;

    #[ORM\Column]
    #[Groups(['course_video:read', 'course:read'])]
    private int $position = 0;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['course_video:read', 'course:read'])]
    private ?string $thumbnail = null;

    #[ORM\Column]
    #[Groups(['course_video:read', 'course:read'])]
    private bool $isFreePreview = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['course_video:read'])]
    private ?DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function __toString(): string
    {
        return $this->title ?? 'Nouvelle vidéo';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;
        return $this;
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

    public function getVideoFile(): ?string
    {
        return $this->videoFile;
    }

    public function setVideoFile(string $videoFile): static
    {
        $this->videoFile = $videoFile;
        return $this;
    }

    /**
     * URL de streaming pour l'API (ne pas exposer le chemin réel)
     */
    #[Groups(['course_video:read'])]
    public function getStreamUrl(): ?string
    {
        if (!$this->videoFile || !$this->id) {
            return null;
        }
        return '/api/courses/videos/' . $this->id . '/stream';
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;
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

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    public function isFreePreview(): bool
    {
        return $this->isFreePreview;
    }

    public function setIsFreePreview(bool $isFreePreview): static
    {
        $this->isFreePreview = $isFreePreview;
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

    /**
     * Retourne la durée formatée (ex: "12:34")
     */
    #[Groups(['course_video:read', 'course:read'])]
    public function getFormattedDuration(): string
    {
        if (!$this->duration) {
            return '00:00';
        }
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
