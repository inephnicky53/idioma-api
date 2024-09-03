<?php

namespace App\Serializer\Normalizer;

use App\Entity\Teacher;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TeacherNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        private readonly Security $security
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
        if ($user && $user->getTeachers()->count() > 0) {
            foreach ($user->getTeachers() as $userTeacher) {
                if ($object === $userTeacher->getTeacher()) {
                    if ($userTeacher->getBuyedAt() !== null)
                        $object->setCanTrial(false);

                    $object->setUserHours($userTeacher->getHours());
                    $object->setIsFavorite(true);
                }
            }
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
