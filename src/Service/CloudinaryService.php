<?php

namespace App\Service;

use Cloudinary\Cloudinary;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CloudinaryService
{
    private Cloudinary $cloudinary;

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
     * Upload a video to Cloudinary
     * 
     * @param UploadedFile|string $file Path to the file or UploadedFile object
     * @param string $publicId Optional public ID
     * @return string|null The secure URL of the uploaded video
     */
    public function uploadVideo(mixed $file, ?string $publicId = null): ?string
    {
        if (!isset($this->cloudinary)) {
            $this->logger->error('Cloudinary not initialized in uploadVideo');
            return null;
        }

        $options = [
            'resource_type' => 'video',
            'folder' => 'idioma_club/videos',
        ];

        if ($publicId) {
            $options['public_id'] = $publicId;
        }

        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        try {
            $result = $this->cloudinary->uploadApi()->upload($filePath, $options);
            $this->logger->info('Cloudinary upload result: ' . $filePath, [
                'options' => $options,
                'publicId' => $publicId,
                'result' => $result
            ]);
            return $result['secure_url'] ?? null;
        } catch (\Exception $e) {
            $this->logger->error('Cloudinary upload failed: ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $filePath
            ]);
            return null;
        }
    }

    /**
     * Generate a signature for direct upload from the frontend
     */
    public function generateSignature(array $paramsToSign): array
    {
        if (empty($this->cloudinaryUrl)) {
            $this->logger->error('Cloudinary URL is empty in generateSignature');
            throw new \Exception('Cloudinary configuration missing');
        }

        // Extraire de manière fiable les identifiants de l'URL
        $url = parse_url($this->cloudinaryUrl);
        $apiKey = $url['user'] ?? '';
        $apiSecret = $url['pass'] ?? '';
        $cloudName = $url['host'] ?? '';
        
        // Liste des paramètres à EXCLURE de la signature selon la doc Cloudinary
        $excludedParams = ['resource_type', 'type', 'api_key', 'cloud_name', 'signature', 'file'];
        
        // Retirer les paramètres vides et les paramètres exclus
        $filteredParams = array_filter($paramsToSign, function($v, $k) use ($excludedParams) { 
            return $v !== null && $v !== '' && !in_array($k, $excludedParams); 
        }, ARRAY_FILTER_USE_BOTH);
        
        // Trier les paramètres par ordre alphabétique
        ksort($filteredParams);
        
        // Créer la chaîne à signer (clé=valeur&clé=valeur...secret)
        $pairs = [];
        foreach ($filteredParams as $key => $value) {
            $pairs[] = "$key=$value";
        }
        $stringToSign = implode('&', $pairs) . $apiSecret;
        
        $this->logger->info('Cloudinary signature generated', [
            'stringToSign' => str_replace($apiSecret, '[SECRET]', $stringToSign),
            'timestamp' => $paramsToSign['timestamp'] ?? null
        ]);

        return [
            'signature' => sha1($stringToSign),
            'timestamp' => $paramsToSign['timestamp'] ?? time(),
            'api_key' => $apiKey,
            'cloud_name' => $cloudName,
        ];
    }
}
