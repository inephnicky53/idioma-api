<?php

namespace App\Contract;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface VideoStorageInterface
{
    /**
     * Upload a video to the storage provider
     *
     * @param UploadedFile|string $file
     * @param array $options
     * @return array{url: string, thumbnail?: string, duration?: int}|null
     */
    public function uploadVideo(mixed $file, array $options = []): ?array;

    /**
     * Get the video URL from the storage provider
     *
     * @param string $videoId
     * @return string|null
     */
    public function getVideoUrl(string $videoId): ?string;
}
