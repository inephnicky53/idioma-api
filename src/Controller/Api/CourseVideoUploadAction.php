<?php

namespace App\Controller\Api;

use App\Entity\Course;
use App\Entity\CourseVideo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur pour l'upload de vidéos de cours
 * POST /api/courses/{courseId}/videos/upload
 * Utilise Vich Uploader pour gérer les uploads
 */
#[AsController]
#[IsGranted('ROLE_ADMIN')]
class CourseVideoUploadAction
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function __invoke(int $courseId, Request $request): Response
    {
        // Vérifier que le cours existe
        $course = $this->entityManager->getRepository(Course::class)->find($courseId);
        if (!$course) {
            throw new NotFoundHttpException('Cours introuvable');
        }

        // Récupérer le fichier vidéo
        $videoFile = $request->files->get('videoFile');
        if (!$videoFile) {
            throw new BadRequestHttpException('Aucun fichier vidéo fourni');
        }

        // Vérifier que c'est bien un UploadedFile
        if (!$videoFile instanceof UploadedFile) {
            throw new BadRequestHttpException('Le fichier fourni n\'est pas valide');
        }

        // Vérifier que le fichier n'a pas d'erreur
        if ($videoFile->getError() !== UPLOAD_ERR_OK) {
            throw new BadRequestHttpException('Erreur lors du téléchargement du fichier: ' . $videoFile->getErrorMessage());
        }

        // Récupérer les données du formulaire
        $title = $request->request->get('title');
        $titleEn = $request->request->get('titleEn');
        $description = $request->request->get('description');
        $duration = $request->request->get('duration');
        $position = $request->request->get('position', 0);
        $isFreePreview = $request->request->get('isFreePreview', false) === 'true';

        // Valider les données requises
        if (!$title) {
            throw new BadRequestHttpException('Le titre (FR) est requis');
        }

        try {
            // Créer l'entité CourseVideo
            $courseVideo = new CourseVideo();
            $courseVideo->setCourse($course);
            $courseVideo->setTitle($title);
            $courseVideo->setTitleEn($titleEn);
            $courseVideo->setDescription($description);
            $courseVideo->setDuration((int) $duration);
            $courseVideo->setPosition((int) $position);
            $courseVideo->setIsFreePreview($isFreePreview);

            // Assigner le fichier vidéo à Vich Uploader
            // Vich gérera automatiquement le stockage et la mise à jour de videoFile
            $courseVideo->setVideoFileUpload($videoFile);

            // Persister et flush
            // Vich Uploader interceptera le flush et gérera l'upload automatiquement
            $this->entityManager->persist($courseVideo);
            $this->entityManager->flush();

            // Retourner la réponse
            return new JsonResponse([
                'success' => true,
                'message' => 'Vidéo uploadée avec succès',
                'video' => [
                    'id' => $courseVideo->getId(),
                    'title' => $courseVideo->getTitle(),
                    'titleEn' => $courseVideo->getTitleEn(),
                    'videoFile' => $courseVideo->getVideoFile(),
                     'cloudinaryUrl' => $courseVideo->getCloudinaryUrl(),
                    'streamUrl' => $courseVideo->getStreamUrl(),
                    'duration' => $courseVideo->getDuration(),
                    'position' => $courseVideo->getPosition(),
                    'isFreePreview' => $courseVideo->isFreePreview(),
                ]
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Erreur lors de l\'upload: ' . $e->getMessage());
        }
    }
}

