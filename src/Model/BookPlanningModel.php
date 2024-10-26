<?php

namespace App\Model;

class BookPlanningModel
{
    public function __construct(
        public ?int $id = null,
        public ?bool $isTrial = false,
    )
    {
    }
}