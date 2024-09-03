<?php

namespace App\Aggregat;

class Translation
{
    private string $domain;
    private string $language;
    private string $key;
    private ?string $content;
    private bool $isICU = false;
}
