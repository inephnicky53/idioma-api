<?php

namespace App\Controller\Api;

use App\Entity\CourseVideo;
use App\Service\VideoManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Action API Platform pour le streaming vidéo
 * Utilise VideoManager pour la logique métier
 */
#[AsController]
class VideoStreamAction
{
    public function __construct(
        private readonly VideoManager $videoManager,
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function __invoke(int $id, Request $request): Response
    {
        $video = $this->entityManager->getRepository(CourseVideo::class)->find($id);

        if (!$video) {
            throw new NotFoundHttpException('Vidéo introuvable');
        }

        // Vérifier l'accès via le Manager
        if (!$this->videoManager->canAccessVideo($video)) {
            throw new AccessDeniedHttpException('Vous devez acheter ce cours pour accéder à cette vidéo');
        }

        // Créer et retourner la réponse de streaming via le Manager
        return $this->videoManager->createStreamResponse($video, $request);
    }
}

