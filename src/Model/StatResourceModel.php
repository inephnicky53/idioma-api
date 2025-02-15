<?php
namespace App\Model;

use Symfony\Component\Serializer\Annotation\Groups;

class StatResourceModel
{
    public function __construct(
        #[Groups(['stat:get'])]
        public string $key,
        
        #[Groups(['stat:get'])]
        public int $value
    )
    {
    }
}