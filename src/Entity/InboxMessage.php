<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\InboxMessageRepository;
use App\State\Inbox\NewMessageProcessor;
use App\State\Teacher\SaveTeacherProcessor;
use App\Trait\Datable;
use App\Trait\Deletable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: InboxMessageRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            security: "is_granted('MESSAGE_READ',object)"
        ),
        new GetCollection(
            security: "is_granted('ROLE_USER')"
        ),
        new GetCollection(
            uriTemplate: '/threads/{threadId}/messages',
            uriVariables: [
                'threadId' => new Link(toProperty: 'thread', fromClass: InboxThread::class),
            ],
            security: "is_granted('ROLE_USER')",
        ),
        new Post(
            denormalizationContext: ['groups' => ['inbox:chat:send']],
        ),
        new Post(
            uriTemplate: "thread/messages/new",
            security: "is_granted('ROLE_USER')",
            processor: NewMessageProcessor::class
        ),
        new Patch(name: 'update')
    ],
    mercure: true,
)]
class InboxMessage
{
    use Datable {
        Datable::__construct as private dateConstructor;
    }
    use Deletable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:inbox:threads'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'inboxMessages')]
    #[Groups(['inbox:chat:send', 'user:inbox:threads'])]
    private ?User $author = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['inbox:chat:send', 'user:inbox:threads'])]
    private ?string $body = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[Groups(['user:inbox:threads'])]
    private ?Attachment $attachment = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'messageTags')]
    private ?self $tagMessage = null;

    #[ORM\OneToMany(mappedBy: 'tagMessage', targetEntity: self::class)]
    private Collection $messageTags;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inbox:chat:send'])]
    private ?InboxThread $thread = null;

    public function __construct()
    {
        $this->dateConstructor();
        $this->messageTags = new ArrayCollection();
    }

    public function __toString(): string
    {
        return "{$this->author} >> {$this->body}";
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getAttachment(): ?Attachment
    {
        return $this->attachment;
    }

    public function setAttachment(?Attachment $attachment): static
    {
        $this->attachment = $attachment;

        return $this;
    }

    public function getTagMessage(): ?self
    {
        return $this->tagMessage;
    }

    public function setTagMessage(?self $tagMessage): static
    {
        $this->tagMessage = $tagMessage;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getMessageTags(): Collection
    {
        return $this->messageTags;
    }

    public function addMessageTag(self $messageTag): static
    {
        if (!$this->messageTags->contains($messageTag)) {
            $this->messageTags->add($messageTag);
            $messageTag->setTagMessage($this);
        }

        return $this;
    }

    public function removeMessageTag(self $messageTag): static
    {
        if ($this->messageTags->removeElement($messageTag)) {
            // set the owning side to null (unless already changed)
            if ($messageTag->getTagMessage() === $this) {
                $messageTag->setTagMessage(null);
            }
        }

        return $this;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(?\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function getThread(): ?InboxThread
    {
        return $this->thread;
    }

    public function setThread(?InboxThread $thread): static
    {
        $this->thread = $thread;

        return $this;
    }
}
