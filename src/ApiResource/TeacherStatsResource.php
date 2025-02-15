<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Model\StatResourceModel;
use App\State\Teacher\TeacherStatsResourceProvider;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ApiResource(
    shortName: "TeacherStatsResource",
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => 'stat:get'],
            provider: TeacherStatsResourceProvider::class
        )
    ]
)]
class TeacherStatsResource
{
    public function __construct(
        #[Groups(['stat:get'])]
        #[ApiProperty(identifier: true)]
        public string $id = '',

        /** @var StatResourceModel[] $hours */
        #[Groups(['stat:get'])]
        public array $hours = [],

        /** @var StatResourceModel[] $document */
        #[Groups(['stat:get'])]
        public array $courses = [],
    )
    {
        $this->id = $id ?: Uuid::v4()->toRfc4122();
    }
}
