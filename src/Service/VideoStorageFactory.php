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

    private function getStorage(): VideoStorageInterface
    {
        return match ($this->videoProvider) {
            'vimeo' => $this->vimeoService,
            'cloudinary' => $this->cloudinaryService,
            default => $this->cloudinaryService,
        };
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
