<?php

namespace App\State\Inbox;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Teacher;
use App\Service\Inbox\InboxManager;

class NewMessageProcessor implements ProcessorInterface
{
    public function __construct(private readonly InboxManager $manager)
    {
    }

    /**
     * @throws \Exception
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Teacher
    {
        return $this->manager->save($data);
    }
}