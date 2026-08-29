<?php

namespace App\Entity;

use App\Model\UploadedFileAwareInterface;
use App\Repository\AttachmentRepository;
use App\Trait\Datable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Annotation\Groups;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
#[Vich\Uploadable]
class Attachment implements Stringable, UploadedFileAwareInterface
{
    use Datable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['article:list', 'establishment:list'])]
    protected ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups([
        'article:list', 'establishment:list',
        'course:list', 'user:courses', 'teacher:list',
        'inbox_thread:read', 'user:inbox',
    ])]
    private string $name = '';

    #[ORM\Column(options: ["unsigned" => true])]
    #[Groups(['article:list', 'establishment:list', 'inbox_thread:read', 'user:inbox'])]
    private int $size = 0;

    #[Vich\UploadableField(mapping: "attachments", fileNameProperty: "name", size: "size", mimeType: "mimeType", originalName: "originalName", dimensions: "dimensions")]
    private ?File $file = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['inbox_thread:read', 'user:inbox'])]
    private ?string $mimeType = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['inbox_thread:read', 'user:inbox'])]
    private ?string $originalName = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $dimensions = null;

    #[ORM\ManyToOne(inversedBy: 'thumbnails')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Course $course = null;

    #[ORM\ManyToOne(inversedBy: 'thumbnails')]
    private ?User $user = null;

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(?string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getDimensions(): ?array
    {
        return $this->dimensions;
    }

    public function setDimensions(?array $dimensions): static
    {
        $this->dimensions = $dimensions;

        return $this;
    }

    public function __construct()
    {
        if (!$this->createdAt) $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id ?: 0;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name ?: '';

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): self
    {
        $this->size = $size ?: 0;

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file = null): self
    {
        $this->file = $file;

        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function __serialize(): array
    {
        return [
            $this->name,
            $this->size,
            $this->file,
            $this->createdAt,
            $this->updatedAt,
        ];
    }

    public function __unserialize(array $data): void
    {
        list(
            $this->name,
            $this->size,
            $this->file,
            $this->createdAt,
            $this->updatedAt,
            ) = $data;
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

    public function getFilePropertyMapping(): array
    {
        return ["name" => 'file'];
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

    #[Groups(['inbox_thread:read', 'user:inbox'])]
    public function getUrl(): string
    {
        $year = $this->createdAt?->format('Y') ?: date('Y');

        return '/uploads/attachments/' . $year . '/' . $this->name;
    }
}

