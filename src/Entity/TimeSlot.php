<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\TimeSlotRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

#[ORM\Entity(repositoryClass: TimeSlotRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get()
    ],
    normalizationContext: ['groups' => ['timeslot:read']],
    denormalizationContext: ['groups' => ['timeslot:write']]
)]
class TimeSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['timeslot:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le jour ne peut pas être vide')]
    #[Assert\Choice(
        choices: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
        message: 'Le jour doit être un jour de la semaine valide'
    )]
    #[Groups(['timeslot:read', 'timeslot:write'])]
    private ?string $day = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['timeslot:read', 'timeslot:write'])]
    private ?string $time = null;

    #[ORM\Column(length: 5, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\d{1,2}:\d{2}$/',
        message: 'Le format de l\'heure de début doit être comme "18:00"'
    )]
    #[Groups(['timeslot:read', 'timeslot:write'])]
    private ?string $startTime = null;

    #[ORM\Column(length: 5, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\d{1,2}:\d{2}$/',
        message: 'Le format de l\'heure de fin doit être comme "20:00"'
    )]
    #[Groups(['timeslot:read', 'timeslot:write'])]
    private ?string $endTime = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'timeSlots')]
    #[Groups(['timeslot:read', 'timeslot:write'])]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDay(): ?string
    {
        return $this->day;
    }

    public function setDay(string $day): static
    {
        $this->day = $day;

        return $this;
    }

    public function getTime(): ?string
    {
        return $this->time;
    }

    public function setTime(string $time): static
    {
        $this->time = $time;

        return $this;
    }

    public function getStartTime(): ?string
    {
        return $this->startTime;
    }

    public function setStartTime(?string $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?string
    {
        return $this->endTime;
    }

    public function setEndTime(?string $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        $this->users->removeElement($user);

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s %s', $this->day, $this->time);
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function syncTimeFields(): void
    {
        // Si startTime et endTime sont définis, synchroniser avec time
        if (!empty($this->startTime) && !empty($this->endTime)) {
            $this->time = $this->startTime . '–' . $this->endTime;
        }
        // Si time est défini, extraire startTime et endTime
        elseif (!empty($this->time) && strpos($this->time, '–') !== false) {
            [$this->startTime, $this->endTime] = explode('–', $this->time);
        }
    }
}
