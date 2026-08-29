<?php

namespace App\Entity;

use App\Repository\PlanningAttendanceRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanningAttendanceRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_planning_attendance_student', columns: ['planning_id', 'student_id'])]
class PlanningAttendance
{
    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LATE = 'late';
    public const STATUS_EXCUSED = 'excused';

    public const SANCTION_NONE = 'none';
    public const SANCTION_WARNING = 'warning';
    public const SANCTION_DEDUCT_HOUR = 'deduct_hour';
    public const SANCTION_REFUND_HOUR = 'refund_hour';

    public const PARTY_STUDENT = 'student';
    public const PARTY_TEACHER = 'teacher';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attendances')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Planning $planning = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $student = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $reportedBy = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PRESENT;

    #[ORM\Column(length: 32)]
    private string $sanction = self::SANCTION_NONE;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private ?DateTimeImmutable $reportedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $sanctionedAt = null;

    #[ORM\Column(length: 20, options: ['default' => self::PARTY_STUDENT])]
    private string $party = self::PARTY_STUDENT;

    #[ORM\Column]
    private float $hoursDeducted = 0;

    #[ORM\Column]
    private float $hoursRefunded = 0;

    public function __construct()
    {
        $this->reportedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlanning(): ?Planning
    {
        return $this->planning;
    }

    public function setPlanning(?Planning $planning): static
    {
        $this->planning = $planning;

        return $this;
    }

    public function getStudent(): ?User
    {
        return $this->student;
    }

    public function setStudent(?User $student): static
    {
        $this->student = $student;

        return $this;
    }

    public function getReportedBy(): ?User
    {
        return $this->reportedBy;
    }

    public function setReportedBy(?User $reportedBy): static
    {
        $this->reportedBy = $reportedBy;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSanction(): string
    {
        return $this->sanction;
    }

    public function setSanction(string $sanction): static
    {
        $this->sanction = $sanction;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getReportedAt(): ?DateTimeImmutable
    {
        return $this->reportedAt;
    }

    public function setReportedAt(DateTimeImmutable $reportedAt): static
    {
        $this->reportedAt = $reportedAt;

        return $this;
    }

    public function getSanctionedAt(): ?DateTimeImmutable
    {
        return $this->sanctionedAt;
    }

    public function setSanctionedAt(?DateTimeImmutable $sanctionedAt): static
    {
        $this->sanctionedAt = $sanctionedAt;

        return $this;
    }

    public function getHoursDeducted(): float
    {
        return $this->hoursDeducted;
    }

    public function setHoursDeducted(float $hoursDeducted): static
    {
        $this->hoursDeducted = $hoursDeducted;

        return $this;
    }

    public function getParty(): string
    {
        return $this->party;
    }

    public function setParty(string $party): static
    {
        $this->party = $party;

        return $this;
    }

    public function getHoursRefunded(): float
    {
        return $this->hoursRefunded;
    }

    public function setHoursRefunded(float $hoursRefunded): static
    {
        $this->hoursRefunded = $hoursRefunded;

        return $this;
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_PRESENT, self::STATUS_ABSENT, self::STATUS_LATE, self::STATUS_EXCUSED];
    }

    /** @return list<string> */
    public static function sanctions(): array
    {
        return [self::SANCTION_NONE, self::SANCTION_WARNING, self::SANCTION_DEDUCT_HOUR, self::SANCTION_REFUND_HOUR];
    }
}
