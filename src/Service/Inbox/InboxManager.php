<?php

namespace App\Service\Inbox;

use App\Entity\InboxMessage;

class InboxManager
{

    public function save(InboxMessage $data)
    {
        dd($data);
    }
}