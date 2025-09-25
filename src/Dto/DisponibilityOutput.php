<?php

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class DisponibilityOutput
{
    #[Groups(['teacher:disponibilities:list'])]
    public int $id;

    #[Groups(['teacher:disponibilities:list'])]
    public string $day;

    #[Groups(['teacher:disponibilities:list'])]
    public string $start;

    #[Groups(['teacher:disponibilities:list'])]
    public string $end;

    #[Groups(['teacher:disponibilities:list'])]
    public bool $isActive;

    public function __construct(
        int $id,
        string $day,
        string $start,
        string $end,
        bool $isActive
    ) {
        $this->id = $id;
        $this->day = $day;
        $this->start = $start;
        $this->end = $end;
        $this->isActive = $isActive;
    }
}