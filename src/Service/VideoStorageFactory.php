<?php

namespace App\Service;

use App\Contract\VideoStorageInterface;

class VideoStorageFactory implements VideoStorageInterface
{
    public function __construct(
        private readonly string $videoProvider,
        private readonly CloudinaryService $cloudinaryService,
        private readonly VimeoService $vimeoService
    ) {
    }

    public function getCloudinaryService(): CloudinaryService
    {
        return $this->cloudinaryService;
    }

    public function getVimeoService(): VimeoService
    {
        return $this->vimeoService;
    }

    public function getStorageForProvider(string $provider): VideoStorageInterface
    {
        return match ($provider) {
            'vimeo' => $this->vimeoService,
            'cloudinary' => $this->cloudinaryService,
            default => $this->cloudinaryService,
        };
    }

    private function getStorage(): VideoStorageInterface
    {
        return $this->getStorageForProvider($this->videoProvider);
    }

    public function uploadVideo(mixed $file, array $options = []): ?array
    {
        return $this->getStorage()->uploadVideo($file, $options);
    }

    public function getVideoUrl(string $videoId): ?string
    {
        return $this->getStorage()->getVideoUrl($videoId);
    }
}
