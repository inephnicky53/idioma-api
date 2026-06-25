<?php

namespace App\EventListener;

use App\Contract\VideoStorageInterface;
use App\Entity\CourseVideo;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Vich\UploaderBundle\Event\Event;

class VideoUploadListener
{
    public function __construct(
        private readonly VideoStorageInterface $videoStorage,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {}

    public function onVichUploaderPostUpload(Event $event): void
    {
        $object = $event->getObject();
        $this->logger->info('VideoUploadListener: Start upload check for object ' . get_class($object));

        if (!$object instanceof CourseVideo) {
            return;
        }

        // Si l'URL Cloudinary ou Vimeo URI est déjà remplie, on ne fait rien
        if ($object->getCloudinaryUrl() || $object->getVimeoUri()) {
            $this->logger->info('VideoUploadListener: Video URL already exists, skipping automatic upload.');
            return;
        }

        $mapping = $event->getMapping();
        $file = $mapping->getFile($object);
        
        if (!$file) {
            $this->logger->warning('VideoUploadListener: No file found in mapping');
            return;
        }

        $path = $file->getRealPath();
        $this->logger->info('VideoUploadListener: Local file path: ' . $path);

        if (!$path || !file_exists($path)) {
            $this->logger->error('VideoUploadListener: File does not exist at path: ' . $path);
            return;
        }

        // Upload vers le fournisseur configuré
        $this->logger->info('VideoUploadListener: Starting upload to configured storage...');
        $result = $this->videoStorage->uploadVideo($path, [
            'title' => $object->getTitle(),
            'description' => $object->getDescription(),
        ]);

        if ($result) {
            $this->logger->info('VideoUploadListener: Upload successful!');
            
            // Update the entity based on provider
            $videoProvider = $_ENV['VIDEO_PROVIDER'] ?? 'cloudinary';
            if ($videoProvider === 'vimeo') {
                $object->setVimeoUri($result['url']);
            } else {
                $object->setCloudinaryUrl($result['url']);
            }

            if ($result['duration']) {
                $object->setDuration((int)$result['duration']);
            }

            if ($result['thumbnail']) {
                $object->setThumbnail($result['thumbnail']);
            }

            $this->entityManager->flush();

            // Supprimer le fichier local pour libérer de l'espace sur le serveur
            @unlink($path);
            $this->logger->info('VideoUploadListener: Local file deleted.');
        } else {
            $this->logger->error('VideoUploadListener: Upload failed (no result returned)');
        }
    }
}
