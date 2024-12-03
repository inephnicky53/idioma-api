<?php

namespace App\Model;

trait Initiable
{
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public static function __set_state(array $data)
    {
        return new self($data);
    }
}