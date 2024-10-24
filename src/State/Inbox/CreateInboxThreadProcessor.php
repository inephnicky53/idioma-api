<?php

namespace App\State\Inbox;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\InboxThread;
use App\Service\Inbox\InboxManager;

class CreateInboxThreadProcessor implements ProcessorInterface
{
    public function __construct(private readonly InboxManager $manager)
    {
    }

    /**
     * @throws \Exception
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InboxThread
    {
        return $this->manager->createThread($data);
    }
}