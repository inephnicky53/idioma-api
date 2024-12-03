<?php

namespace App\Serializer\Normalizer;

use App\Entity\Teacher;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class TeacherNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $normalizer,
        private Security            $security
    )
    {
    }

    /**
     * @param Teacher $object
     * @throws ExceptionInterface
     */
    public function normalize($object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        /** @var User $user */
        $user = $this->security->getUser();

        if ($user) {
            $userTeacher = $user->getTeachers()->filter(fn(Teacher $t) => $t->getId() === $object->getId())[0];
            $object->setCanTrial(!$userTeacher);
            $object->setHours($userTeacher?->getHours() ?? 0);
        }

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Teacher;
    }

    public function getSupportedTypes(?string $format = null): array
    {
        return [Teacher::class => true];
    }
}
