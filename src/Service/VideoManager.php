<?php

namespace App\Service;

use App\Entity\CoursePurchase;
use App\Entity\CourseVideo;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Manager pour la logique métier des vidéos de cours
 */
class VideoManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
    ) {}

    /**
     * Vérifie si l'utilisateur courant peut accéder à la vidéo
     */
    public function canAccessVideo(CourseVideo $video): bool
    {
        // Aperçu gratuit accessible à tous les utilisateurs connectés
        if ($video->isFreePreview()) {
            return true;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Admin a accès à tout
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Vérifier si l'utilisateur a acheté le cours
        $course = $video->getCourse();
        if (!$course) {
            return false;
        }

        $purchase = $this->entityManager->getRepository(CoursePurchase::class)
            ->findOneBy([
                'user' => $user,
                'course' => $course,
                'isActive' => true
            ]);

        return $purchase !== null;
    }

    /**
     * Retourne le chemin complet du fichier vidéo
     */
    public function getVideoPath(CourseVideo $video): string
    {
        return $this->projectDir . '/public/uploads/videos/' . $video->getVideoFile();
    }

    /**
     * Vérifie si le fichier vidéo existe
     */
    public function videoFileExists(CourseVideo $video): bool
    {
        return file_exists($this->getVideoPath($video));
    }

    /**
     * Crée une réponse de streaming pour la vidéo
     */
    public function createStreamResponse(CourseVideo $video, Request $request): StreamedResponse
    {
        $videoPath = $this->getVideoPath($video);

        if (!file_exists($videoPath)) {
            throw new NotFoundHttpException('Fichier vidéo introuvable');
        }

        $fileSize = filesize($videoPath);
        $mimeType = mime_content_type($videoPath) ?: 'video/mp4';

        // Support du Range Request pour le streaming
        $start = 0;
        $end = $fileSize - 1;
        $statusCode = 200;

        if ($request->headers->has('Range')) {
            $range = $request->headers->get('Range');
            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = (int) $matches[1];
                $end = $matches[2] !== '' ? (int) $matches[2] : $fileSize - 1;
                $statusCode = 206; // Partial Content
            }
        }

        $length = $end - $start + 1;

        $response = new StreamedResponse(function () use ($videoPath, $start, $length) {
            $handle = fopen($videoPath, 'rb');
            if (!$handle) {
                throw new \RuntimeException('Impossible d\'ouvrir le fichier vidéo');
            }

            fseek($handle, $start);
            $remaining = $length;
            // Augmenter la taille du chunk pour un meilleur débit (256KB au lieu de 8KB)
            $chunkSize = 262144; // 256 * 1024 bytes

            while ($remaining > 0 && !feof($handle)) {
                $read = min($chunkSize, $remaining);
                $data = fread($handle, $read);
                if ($data === false) {
                    break;
                }
                echo $data;
                $remaining -= strlen($data);
                // Flush après chaque chunk pour un streaming progressif
                flush();
                // Vérifier si la connexion est toujours active
                if (connection_aborted()) {
                    break;
                }
            }

            fclose($handle);
        }, $statusCode);

        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Length', (string) $length);
        $response->headers->set('Accept-Ranges', 'bytes');

        // Ajouter Content-Range UNIQUEMENT pour les Range Requests (206)
        if ($statusCode === 206) {
            $response->headers->set('Content-Range', sprintf('bytes %d-%d/%d', $start, $end, $fileSize));
        }

        // Headers pour éviter le buffering et le caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        // Headers pour améliorer la compatibilité du streaming
        $response->headers->set('Connection', 'keep-alive');

        // NE PAS utiliser Transfer-Encoding: chunked avec Content-Length
        // Cela cause des conflits et des problèmes de streaming
        // Laisser Symfony gérer l'encodage automatiquement

        return $response;
    }
}

