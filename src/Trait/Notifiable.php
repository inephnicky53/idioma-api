<?php

namespace App\Trait;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

trait Notifiable
{
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $notificationsReadAt = null;

    public function getNotificationsReadAt(): ?DateTimeImmutable
    {
        return $this->notificationsReadAt;
    }

    public function setNotificationsReadAt(?DateTimeImmutable $notificationsReadAt): self
    {
        $this->notificationsReadAt = $notificationsReadAt;

        return $this;
    }
}