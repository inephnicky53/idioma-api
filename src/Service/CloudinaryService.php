<?php

namespace App\Service;

use App\Contract\VideoStorageInterface;
use Cloudinary\Cloudinary;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CloudinaryService implements VideoStorageInterface
{
    private ?Cloudinary $cloudinary = null;

    public function __construct(
        private readonly string $cloudinaryUrl,
        private readonly LoggerInterface $logger
    ) {
        if ($this->cloudinaryUrl) {
            try {
                $this->cloudinary = new Cloudinary($this->cloudinaryUrl);
            } catch (\Exception $e) {
                $this->logger->error('Failed to initialize Cloudinary: ' . $e->getMessage());
            }
        } else {
            $this->logger->warning('Cloudinary URL not configured');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function uploadVideo(mixed $file, array $options = []): ?array
    {
        if (!$this->cloudinary) {
            $this->logger->error('Cloudinary not initialized');
            return null;
        }

        $defaultOptions = [
            'resource_type' => 'video',
            'folder' => 'idioma_club/videos',
        ];
        $options = array_merge($defaultOptions, $options);

        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        try {
            $result = $this->cloudinary->uploadApi()->upload($filePath, $options);
            $this->logger->info('Cloudinary upload successful', ['file' => $filePath]);
            
            return [
                'url' => $result['secure_url'] ?? null,
                'thumbnail' => $result['thumbnail_url'] ?? null,
                'duration' => $result['duration'] ?? null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Cloudinary upload failed: ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $filePath
            ]);
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getVideoUrl(string $videoId): ?string
    {
        if (!$this->cloudinary) {
            return null;
        }

        return "https://res.cloudinary.com/" . $this->cloudinary->getCloudName() . "/video/upload/" . $videoId;
    }

    /**
     * Generate a signature for direct upload from the frontend
     */
    public function generateSignature(array $paramsToSign): array
    {
        if (!$this->cloudinary || empty($this->cloudinaryUrl)) {
            $this->logger->error('Cloudinary configuration missing');
            throw new \Exception('Cloudinary configuration missing');
        }

        $url = parse_url($this->cloudinaryUrl);
        $apiKey = $url['user'] ?? '';
        $apiSecret = $url['pass'] ?? '';
        $cloudName = $url['host'] ?? '';

        $excludedParams = ['resource_type', 'type', 'api_key', 'cloud_name', 'signature', 'file'];
        $filteredParams = array_filter($paramsToSign, function($v, $k) use ($excludedParams) { 
            return $v !== null && $v !== '' && !in_array($k, $excludedParams); 
        }, ARRAY_FILTER_USE_BOTH);
        
        ksort($filteredParams);
        $pairs = [];
        foreach ($filteredParams as $key => $value) {
            $pairs[] = "$key=$value";
        }
        $stringToSign = implode('&', $pairs) . $apiSecret;

        return [
            'signature' => sha1($stringToSign),
            'timestamp' => $paramsToSign['timestamp'] ?? time(),
            'api_key' => $apiKey,
            'cloud_name' => $cloudName,
        ];
    }
}
