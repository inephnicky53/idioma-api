<?php

namespace App\Service;

use App\Contract\VideoStorageInterface;
use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vimeo\Vimeo;

class VimeoService implements VideoStorageInterface
{
    private ?Vimeo $vimeo = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $accessToken,
        private readonly LoggerInterface $logger
    ) {
        if (empty($clientId) || empty($clientSecret) || empty($accessToken)) {
            $this->logger->warning('Vimeo configuration missing', [
                'clientId' => !empty($clientId),
                'clientSecret' => !empty($clientSecret),
                'accessToken' => !empty($accessToken),
            ]);
            throw new \RuntimeException('Vimeo configuration is missing: please set VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, and VIMEO_ACCESS_TOKEN in .env');
        }

        try {
            $this->vimeo = new Vimeo($clientId, $clientSecret, $accessToken);
            $this->logger->info('Vimeo initialized successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize Vimeo: ' . $e->getMessage(), ['exception' => $e]);
            throw new \RuntimeException('Failed to initialize Vimeo: ' . $e->getMessage(), 0, $e);
        }
    }

    public function generateVideoJwt(string $videoUri): string
    {
        // Strip query parameters from URI/URL first
        $cleanedUri = explode('?', $videoUri)[0];
        
        // Extract video ID from URI or URL
        $videoId = null;
        if (preg_match('#(?:/videos/|vimeo\.com/)(\d+)#', $cleanedUri, $matches)) {
            $videoId = $matches[1];
        }
        
        if (!$videoId) {
            $videoId = $videoUri;
        }
        
        $payload = [
            'iss' => $this->clientId,
            'exp' => time() + 3600, // 1 hour expiration
            'aud' => 'vimeo',
            'sub' => $videoId,
        ];

        $jwt = JWT::encode($payload, $this->clientSecret, 'HS256');
        
        $this->logger->info('Generated Vimeo JWT for video', ['videoUri' => $videoUri, 'cleanedUri' => $cleanedUri, 'videoId' => $videoId]);
        
        return $jwt;
    }

    /**
     * {@inheritDoc}
     */
    public function uploadVideo(mixed $file, array $options = []): ?array
    {
        if (!$this->vimeo) {
            throw new \RuntimeException('Vimeo service is not initialized');
        }

        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if (!file_exists($filePath)) {
            $this->logger->error('Vimeo upload failed: file not found', ['file' => $filePath]);
            throw new \RuntimeException('Upload file not found: ' . $filePath);
        }

        $defaultOptions = [
            'name' => $options['title'] ?? 'Video ' . date('Y-m-d H:i:s'),
            'description' => $options['description'] ?? '',
        ];
        $uploadOptions = array_merge($defaultOptions, $options);

        try {
            $this->logger->info('Starting Vimeo upload', ['filePath' => $filePath, 'options' => $uploadOptions]);

            // Upload the video
            $response = $this->vimeo->upload($filePath, $uploadOptions);
            $videoUri = $response;
            $this->logger->info('Vimeo upload successful, got URI', ['videoUri' => $videoUri]);

            // Get video details
            $videoDetails = $this->vimeo->request($videoUri . '?fields=link,pictures.sizes,duration');
            $this->logger->debug('Vimeo video details', ['details' => $videoDetails]);

            if ($videoDetails['status'] !== 200) {
                throw new \RuntimeException('Failed to get video details from Vimeo: HTTP ' . $videoDetails['status']);
            }

            $url = $videoDetails['body']['link'] ?? null;
            $thumbnail = null;
            if (isset($videoDetails['body']['pictures']['sizes']) && is_array($videoDetails['body']['pictures']['sizes'])) {
                $thumbnail = end($videoDetails['body']['pictures']['sizes'])['link'] ?? null;
            }
            $duration = $videoDetails['body']['duration'] ?? null;

            return [
                'url' => $url,
                'thumbnail' => $thumbnail,
                'duration' => $duration,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Vimeo upload failed: ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException('Vimeo upload failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getVideoUrl(string $videoId): ?string
    {
        // If videoId is already a full URL, return it
        if (str_starts_with($videoId, 'http')) {
            return $videoId;
        }

        // Otherwise, assume it's a Vimeo ID or URI
        if (str_starts_with($videoId, '/videos/')) {
            return 'https://vimeo.com' . $videoId;
        }

        return 'https://vimeo.com/' . $videoId;
    }
}
