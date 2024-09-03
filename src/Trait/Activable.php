<?php

namespace App\Trait;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait Activable
{
    #[ORM\Column]
    #[Groups(['article:list', 'article:new', 'teacher:list'])]
    private ?bool $isActive;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $activatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $deactivatedAt = null;

    #[ORM\ManyToOne]
    private ?User $activatedBy = null;

    public function __construct()
    {
        $this->isActive = true;
        $this->activatedAt = new DateTimeImmutable();
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getIsActiveView(?bool $feminine = false): string
    {
        $gender = $feminine ? 've' : 'f';
        $status = $this->isIsActive() ? 'success' : 'danger';
        $text = $this->isIsActive() ? 'Acti' . $gender : 'Inacti' . $gender;
        return "<span class='badge badge-soft-{$status}'>$text</span>";
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getActivatedAt(): ?DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function setActivatedAt(?DateTimeImmutable $activatedAt): self
    {
        $this->activatedAt = $activatedAt;

        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getDeactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function setDeactivatedAt(?DateTimeImmutable $deactivatedAt): self
    {
        $this->deactivatedAt = $deactivatedAt;

        return $this;
    }

    public function getActivatedBy(): ?User
    {
        return $this->activatedBy;
    }

    public function setActivatedBy(?User $activatedBy): self
    {
        $this->activatedBy = $activatedBy;

        return $this;
    }
}
