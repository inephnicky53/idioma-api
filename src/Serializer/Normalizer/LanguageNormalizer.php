<?php

namespace App\Serializer\Normalizer;

use App\Entity\Language;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class LanguageNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $normalizer,
        private TeacherRepository $repository
    )
    {
    }

    /**
     * @param Language $object
     * @throws ExceptionInterface
     */
    public function normalize($object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $count = $this->repository->countTeachersByTeachingLanguage($object);
        $object->setTeachers($count || 0);

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Language;
    }

    public function getSupportedTypes(?string $format = null): array
    {
        return [Language::class => true];
    }
}
