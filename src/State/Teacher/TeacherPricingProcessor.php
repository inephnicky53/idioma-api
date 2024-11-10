<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

readonly class TeacherPricingProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
    )
    {
    }


    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
