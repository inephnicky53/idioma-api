<?php

namespace App\EventListener;

use App\Entity\CourseVideo;
use App\Service\CloudinaryService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Vich\UploaderBundle\Event\Event;

class VideoUploadListener
{
    public function __construct(
        private readonly CloudinaryService $cloudinaryService,
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

        // Si l'URL Cloudinary est déjà remplie (via upload direct), on ne fait rien
        if ($object->getCloudinaryUrl()) {
            $this->logger->info('VideoUploadListener: Cloudinary URL already exists, skipping automatic upload.');
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

        // Upload vers Cloudinary
        $this->logger->info('VideoUploadListener: Starting Cloudinary upload...');
        $cloudinaryUrl = $this->cloudinaryService->uploadVideo($path);

        if ($cloudinaryUrl) {
            $this->logger->info('VideoUploadListener: Upload successful! URL: ' . $cloudinaryUrl);
            // Mettre à jour l'objet pour refléter les changements
            $object->setCloudinaryUrl($cloudinaryUrl);
            
            // Mettre à jour l'URL Cloudinary et vider le nom du fichier local dans la base de données
            // car le fichier local sera supprimé.
            $this->entityManager->createQueryBuilder()
                ->update(CourseVideo::class, 'cv')
                ->set('cv.cloudinaryUrl', ':url')
                ->set('cv.videoFile', ':null')
                ->where('cv.id = :id')
                ->setParameter('url', $cloudinaryUrl)
                ->setParameter('null', null)
                ->setParameter('id', $object->getId())
                ->getQuery()
                ->execute();

            // Supprimer le fichier local pour libérer de l'espace sur le serveur
            @unlink($path);
            $this->logger->info('VideoUploadListener: Local file deleted.');
        } else {
            $this->logger->error('VideoUploadListener: Cloudinary upload failed (no URL returned)');
        }
    }
}
