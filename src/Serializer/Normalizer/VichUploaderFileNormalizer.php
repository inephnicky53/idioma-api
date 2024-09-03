<?php

namespace App\Serializer\Normalizer;

use App\Model\UploadedFileAwareInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class VichUploaderFileNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'APP_VICHUPLDFILE_ALREADY_CALLED';

    private $helper;
    protected $requestStack;

    public function __construct(UploaderHelper $helper, RequestStack $requestStack)
    {
        $this->helper = $helper;
        $this->requestStack = $requestStack;
    }

    public function normalize($object, $format = null, array $context = array()): array
    {
        $context[self::ALREADY_CALLED] = true;
        $data = $this->normalizer->normalize($object, $format, $context);

        /** @var UploadedFileAwareInterface $object */
        foreach($object->getFilePropertyMapping() as $key => $value) {
            if(isset($data[$key])) {
                $data[$key] = $this->helper->asset($object, $value);
                if($data[$key]) {
                    $request = $this->requestStack->getCurrentRequest();
                    $data[$key] = $request->getUriForPath($data[$key]);
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
}