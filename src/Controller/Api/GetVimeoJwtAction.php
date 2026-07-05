<?php

namespace App\Controller\Api;

use App\Entity\CourseVideo;
use App\Service\VideoManager;
use App\Service\VimeoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Action API Platform pour récupérer un JWT Vimeo
 */
#[AsController]
class GetVimeoJwtAction
{
    public function __construct(
        private readonly VideoManager $videoManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly VimeoService $vimeoService
    ) {}

    public function __invoke(int $id): JsonResponse
    {
        $video = $this->entityManager->getRepository(CourseVideo::class)->find($id);

        if (!$video) {
            throw new NotFoundHttpException('Vidéo introuvable');
        }

        if (!$this->videoManager->canAccessVideo($video)) {
            throw new AccessDeniedHttpException('Vous devez acheter ce cours pour accéder à cette vidéo');
        }

        if (!$video->getVimeoUri()) {
            throw new NotFoundHttpException('Cette vidéo n\'est pas hébergée sur Vimeo');
        }

        $jwt = $this->vimeoService->generateVideoJwt($video->getVimeoUri());

        return new JsonResponse([
            'jwt' => $jwt,
            'vimeoUri' => $video->getVimeoUri()
        ]);
    }
}
