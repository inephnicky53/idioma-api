<?php

namespace App\Trait;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait ArrayableTrait
{
    public function toArray(): array
    {
        $records = [];

        foreach ($this as $key => $value) {
            $records[$key] = $value;
        }

        return $records;
    }

    public function get(string $name)
    {
        return $this->toArray()[$name];
    }
}