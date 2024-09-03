<?php

namespace App\Entity;

use App\Repository\UserCourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UserCourseRepository::class)]
class UserCourse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:courses'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'studentCourses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user:courses'])]
    private ?Course $course = null;

    #[ORM\Column]
    #[Groups(['user:courses'])]
    private ?\DateTimeImmutable $addedAt = null;

    #[ORM\Column(options: ["default" => 0])]
    #[Groups(['user:courses'])]
    private ?int $records = 0;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:courses'])]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:courses'])]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column]
    #[Groups(['user:courses'])]
    private ?bool $isBuyed = false;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:courses'])]
    private ?\DateTimeImmutable $buyedAt = null;

    #[ORM\ManyToOne(inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userCourses')]
    private ?Order $command = null;

    #[ORM\Column]
    private ?float $amount = null;

    #[ORM\ManyToOne]
    private ?Currency $currency = null;


    public function __construct()
    {
        $this->addedAt = new \DateTimeImmutable();
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

    public function getAddedAt(): ?\DateTimeImmutable
    {
        return $this->addedAt;
    }

    public function setAddedAt(\DateTimeImmutable $addedAt): static
    {
        $this->addedAt = $addedAt;

        return $this;
    }

    public function getRecords(): ?int
    {
        return $this->records;
    }

    public function setRecords(int $records): static
    {
        $this->records = $records;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(?\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function isIsBuyed(): ?bool
    {
        return $this->isBuyed;
    }

    public function setIsBuyed(bool $isBuyed): static
    {
        $this->isBuyed = $isBuyed;

        return $this;
    }

    public function getBuyedAt(): ?\DateTimeImmutable
    {
        return $this->buyedAt;
    }

    public function setBuyedAt(?\DateTimeImmutable $buyedAt): static
    {
        $this->buyedAt = $buyedAt;

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

    public function getCommand(): ?Order
    {
        return $this->command;
    }

    public function setCommand(?Order $command): static
    {
        $this->command = $command;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }
}
