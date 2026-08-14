<?php

namespace App\Model;

class LanguageModel
{
    public function __construct(
        public string $language,
        public ?string $level = null
    )
    {
    }
}