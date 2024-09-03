<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\Inbox\UserThreadController;
use App\Repository\InboxThreadRepository;
use App\Trait\Datable;
use App\Trait\Deletable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: InboxThreadRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            denormalizationContext: ['groups' => ['inbox:new']],
        ),
        new Get(
            uriTemplate: "user/inbox/threads",
            controller: UserThreadController::class,
            normalizationContext: ['groups' => ['user:inbox', 'user:inbox:threads']],
            security: "is_granted('ROLE_USER')",
        ),
    ],
    mercure: true,
)]
class InboxThread
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

    #[ORM\OneToMany(targetEntity: InboxMessage::class, mappedBy: 'thread', cascade: ['persist'], orphanRemoval: true)]
    private Collection $messages;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'inboxThreads')]
    #[Groups(['inbox:new', 'user:inbox'])]
    private Collection $participants;

    #[ORM\ManyToOne]
    #[Groups(['inbox:new', 'user:inbox:threads'])]
    private ?Teacher $teacher = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[Groups(['inbox:new', 'user:inbox:threads'])]
    private ?Course $course = null;

    public function __construct()
    {
        $this->dateConstructor();
        $this->messages = new ArrayCollection();
        $this->participants = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getTeacher()->getUser()->getFullname();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, InboxMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(InboxMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setThread($this);
        }

        return $this;
    }

    public function removeMessage(InboxMessage $message): static
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getThread() === $this) {
                $message->setThread(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(User $participant): static
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
        }

        return $this;
    }

    public function removeParticipant(User $participant): static
    {
        $this->participants->removeElement($participant);

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


    #[Groups(['user:inbox:threads'])]
    public function getLastMessage(): mixed
    {
        return $this->messages->last();
    }
}
