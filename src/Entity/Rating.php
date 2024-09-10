<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\Rating\ApiPostRatingController;
use App\Repository\RatingRepository;
use App\State\Teacher\TeacherGetCommentProvider;
use App\Trait\Datable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use function Symfony\Component\Translation\t;

#[ORM\Entity(repositoryClass: RatingRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: 'teachers/{teacherId}/comments',
            uriVariables: [
                'teacherId' => new Link(toProperty: 'teacher', fromClass: Teacher::class),
            ],
        ),
        new Post(
            controller: ApiPostRatingController::class,
            denormalizationContext: ['groups' => ['rating:new']],
            security: "is_granted('ROLE_USER')",
        ),
    ],
    normalizationContext: ['groups' => ['rating:list']]
)]
class Rating
{
    use Datable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['rating:list'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['rating:list', 'rating:new'])]
    private ?float $stars = null;

    #[ORM\ManyToOne(inversedBy: 'ratings')]
    #[Groups(['rating:list', 'rating:new'])]
    private ?Teacher $teacher = null;

    #[ORM\ManyToOne(inversedBy: 'ratings')]
    #[Groups(['rating:list', 'rating:new'])]
    private ?Course $course = null;

    #[ORM\ManyToOne(inversedBy: 'ratings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['rating:list', 'rating:new'])]
    private ?string $comment = null;

    public function __construct()
    {
        if (is_null($this->createdAt))
            $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->comment ?? t('comment.empty');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStars(): ?float
    {
        return $this->stars;
    }

    public function setStars(float $stars): static
    {
        $this->stars = $stars;

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

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;

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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }
}
