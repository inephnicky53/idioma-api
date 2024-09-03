<?php

namespace App\Aggregat;

use ApiPlatform\Metadata\GetCollection;
use App\State\Language\LevelGetProvider;
use function Symfony\Component\Translation\t;

#[GetCollection(
    uriTemplate: "language/levels",
    provider: LevelGetProvider::class
)]
class LanguageLevel
{
    private string $level;
    private string $name;


    public function __construct($level)
    {
        $this->level = $level;
        $this->name = t($level);
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
