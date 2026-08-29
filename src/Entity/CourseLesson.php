<?php

namespace App\Entity;

use App\Repository\CourseLessonRepository;
use App\Service\Media\VimeoUrl;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CourseLessonRepository::class)]
class CourseLesson
{
    public const TYPE_VIDEO = 'video';
    public const TYPE_ARTICLE = 'article';
    public const TYPE_QUIZ = 'quiz';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['course:list'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lessons')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CourseSection $section = null;

    #[ORM\Column(length: 255)]
    #[Groups(['course:list'])]
    private ?string $title = null;

    #[ORM\Column(length: 20, options: ['default' => self::TYPE_VIDEO])]
    #[Groups(['course:list'])]
    private ?string $type = self::TYPE_VIDEO;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['course:list'])]
    private ?int $durationMinutes = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['course:list'])]
    private ?int $position = 0;

    /** Free preview, viewable before purchase. */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['course:list'])]
    private ?bool $isPreview = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $vimeoUrl = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSection(): ?CourseSection
    {
        return $this->section;
    }

    public function setSection(?CourseSection $section): static
    {
        $this->section = $section;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isIsPreview(): ?bool
    {
        return $this->isPreview;
    }

    public function setIsPreview(bool $isPreview): static
    {
        $this->isPreview = $isPreview;

        return $this;
    }

    public function getVimeoUrl(): ?string
    {
        return $this->vimeoUrl;
    }

    public function setVimeoUrl(?string $vimeoUrl): static
    {
        $this->vimeoUrl = $vimeoUrl ?: null;

        return $this;
    }

    #[Groups(['course:list'])]
    public function hasVimeo(): bool
    {
        return (bool) $this->vimeoUrl;
    }

    #[Groups(['course:list'])]
    public function getPreviewEmbedUrl(): ?string
    {
        if (!$this->isPreview || !$this->vimeoUrl) {
            return null;
        }

        return VimeoUrl::toEmbed($this->vimeoUrl);
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }
}
