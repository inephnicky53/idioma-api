<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\PlanningRepository;
use App\State\Planning\PlanningBookProcessor;
use App\State\Planning\PlanningCancelProcessor;
use App\State\Planning\PlanningCreateProcessor;
use App\State\Planning\StartPlanningProcessor;
use App\State\Planning\UserPlanningProvider;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: "user/plannings",
            security: "is_granted('ROLE_USER')",
            provider: UserPlanningProvider::class
        ),
        new Post(
            uriTemplate: "plannings/create",
            denormalizationContext: ['groups' => ['planning:create']],
            security: "is_granted('ROLE_USER')",
            processor: PlanningCreateProcessor::class
        ),
        new Post(
            uriTemplate: "plannings/book",
            denormalizationContext: ['groups' => ['planning:create']],
            security: "is_granted('ROLE_USER')",
            processor: PlanningBookProcessor::class
        ),
        new Patch(
            uriTemplate: "plannings/{id}/start",
            denormalizationContext: ['groups' => ['planning:start']],
            security: "is_granted('ROLE_USER') and is_granted('PLANNING_MANAGE', object)",
            processor: StartPlanningProcessor::class
        ),
        new Patch(
            uriTemplate: "plannings/{id}/link",
            denormalizationContext: ['groups' => ['planning:meeting:link']],
            security: "is_granted('ROLE_USER') and is_granted('PLANNING_MANAGE', object)",
            processor: PersistProcessor::class
        ),
        new Delete(
            uriTemplate: "plannings/{id}/cancel",
            security: "is_granted('ROLE_USER') and is_granted('PLANNING_MANAGE', object)",
            processor: PlanningCancelProcessor::class
        ),
    ],
    normalizationContext: ['groups' => ['planning:show']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact'
])]
#[ORM\Entity(repositoryClass: PlanningRepository::class)]
class Planning
{
    const STATUS_CREATED = "planning.status.created";
    const STATUS_STARTED = "planning.status.started";
    const STATUS_PENDING = "planning.status.pending";
    const STATUS_PAUSED = "planning.status.paused";
    const STATUS_REJECTED = "planning.status.rejected";
    const STATUS_CANCELED = "planning.status.canceled";
    const STATUS_FINISHED = "planning.status.finished";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['planning:show'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['planning:show', 'planning:create'])]
    private ?DateTimeImmutable $start;

    #[ORM\Column]
    #[Groups(['planning:show', 'planning:create'])]
    private ?DateTimeImmutable $end = null;

    #[ORM\ManyToOne(inversedBy: 'plannings')]
    #[Groups(['planning:show', 'planning:create'])]
    private ?Teacher $teacher = null;

    #[ORM\ManyToOne(inversedBy: 'plannings')]
    #[Groups(['planning:show'])]
    private ?Course $course = null;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'plannings')]
    #[Groups(['planning:show'])]
    private Collection $participants;

    /**
     * A free first session with this teacher: no prepaid hour is consumed when
     * booking it, and none is refunded when cancelling it. Must be persisted —
     * as a plain property it reverted to false on reload, so cancelling a trial
     * credited an hour that was never paid for.
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['planning:show'])]
    private bool $isTrial = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['planning:show', 'planning:create', 'planning:meeting:link'])]
    private ?string $meetingLink = null;

    #[ORM\Column(length: 255)]
    #[Groups(['planning:show'])]
    private ?string $status;

    #[ORM\Column]
    #[Groups(['planning:show'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'planning', targetEntity: PlanningAttendance::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $attendances;

    #[ORM\OneToOne(mappedBy: 'planning', targetEntity: InboxThread::class)]
    private ?InboxThread $salonThread = null;

    public function __construct()
    {
        if (is_null($this->createdAt))
            $this->createdAt = new DateTimeImmutable();
        $this->start = (new DateTimeImmutable())->modify('60 minutes');
        $this->participants = new ArrayCollection();
        $this->attendances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStart(): ?DateTimeImmutable
    {
        return $this->start;
    }

    public function setStart(DateTimeImmutable $start): static
    {
        $this->start = $start;

        return $this;
    }

    public function getEnd(): ?DateTimeImmutable
    {
        return $this->end;
    }

    public function setEnd(DateTimeImmutable $end): static
    {
        $this->end = $end;

        return $this;
    }

    public function isFree(): bool
    {
        $now = new DateTimeImmutable('now');
        return $this->start > $now;
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

    public function isTrial(): bool
    {
        return $this->isTrial;
    }

    public function setIsTrial(bool $isTrial): static
    {
        $this->isTrial = $isTrial;

        return $this;
    }

    public function getMeetingLink(): ?string
    {
        return $this->meetingLink;
    }

    public function setMeetingLink(?string $meetingLink): static
    {
        $this->meetingLink = $meetingLink;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public static function getStatusList(): array
    {
        return [
            self::STATUS_CREATED,
            self::STATUS_PENDING,
            self::STATUS_PAUSED,
            self::STATUS_CANCELED,
            self::STATUS_REJECTED,
            self::STATUS_FINISHED,
        ];
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, PlanningAttendance>
     */
    public function getAttendances(): Collection
    {
        return $this->attendances;
    }

    public function getSalonThread(): ?InboxThread
    {
        return $this->salonThread;
    }

    public function setSalonThread(?InboxThread $salonThread): static
    {
        $this->salonThread = $salonThread;
        if ($salonThread && $salonThread->getPlanning() !== $this) {
            $salonThread->setPlanning($this);
        }

        return $this;
    }

    public function isSalon(): bool
    {
        return $this->participants->count() > 1;
    }
}
