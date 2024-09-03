<?php


namespace App\Serializer;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class UploadFileDenormalizer implements DenormalizerInterface
{

    public function denormalize($data, string $type, string $format = null, array $context = []): UploadedFile
    {
        return $data;
    }

    public function supportsDenormalization($data, string $type, string $format = null, array $context = []): bool
    {
        return $data instanceof UploadedFile;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [];
    }
}