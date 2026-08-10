<?php

namespace App\State\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\CreateTeacherInput;
use App\Entity\Teacher;
use App\Model\CreateTeacherModel;
use App\Service\Teacher\TeacherManager;

readonly class CreateTeacherProcessor implements ProcessorInterface
{
    public function __construct(private TeacherManager $manager)
    {
    }


    /**
     * @throws \Exception
     * @var CreateTeacherInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Teacher
    {
        return $this->manager->create(new CreateTeacherModel(
            $data->fullname,
            $data->firstname,
            $data->lastname,
            $data->country,
            $data->language,
            $data->phone,
            $data->price,
            $data->currency,
            $data->profile,
            $data->video,
            $data->link,
            $data->shortDescription,
            $data->description,
            $data->experience,
            $data->motivation,
            $data->hookTitle,
            $data->timezone,
            $data->certifications,
            $data->formations,
            $data->availabilities,
            $data->spokenLanguages,
            $data->languages
        ));
    }
}
