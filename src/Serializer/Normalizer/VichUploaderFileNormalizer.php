<?php

namespace App\Serializer\Normalizer;

use App\Model\UploadedFileAwareInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer pour générer les URLs absolues des fichiers uploadés
 * Utilisé par les entités implémentant UploadedFileAwareInterface
 */
class VichUploaderFileNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'APP_VICHUPLDFILE_ALREADY_CALLED';
    private const UPLOAD_BASE_PATH = '/uploads/';

    protected $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function normalize($object, $format = null, array $context = array()): array
    {
        $context[self::ALREADY_CALLED] = true;
        $data = $this->normalizer->normalize($object, $format, $context);

        /** @var UploadedFileAwareInterface $object */
        foreach ($object->getFilePropertyMapping() as $key => $value) {
            if (isset($data[$key]) && $data[$key]) {
                $filePath = trim($data[$key]); // Trim whitespace to fix leading spaces

                // Si le chemin commence déjà par /uploads/, l'utiliser directement
                if (str_starts_with($filePath, '/uploads/')) {
                    $relativePath = $filePath;
                } else {
                    // Sinon, construire l'URL avec le répertoire approprié
                    $relativePath = self::UPLOAD_BASE_PATH . $this->getUploadDir($object, $key) . '/' . $filePath;
                }

                // Obtenir l'URL complète avec le domaine
                $request = $this->requestStack->getCurrentRequest();
                if ($request) {
                    $data[$key] = $request->getUriForPath($relativePath);
                } else {
                    $data[$key] = $relativePath;
                }
            }
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof UploadedFileAwareInterface;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            "*" => false
        ];
    }

    /**
     * Détermine le répertoire d'upload basé sur le type d'entité et la propriété
     */
    private function getUploadDir(object $object, string $propertyName = ''): string
    {
        $className = (new \ReflectionClass($object))->getShortName();

        // Cas spécial pour les ebooks
        if ($propertyName === 'ebookPath') {
            return 'ebooks';
        }

        return match($className) {
            'Course' => 'courses',
            'CourseVideo' => 'videos',
            default => 'files',
        };
    }
}
