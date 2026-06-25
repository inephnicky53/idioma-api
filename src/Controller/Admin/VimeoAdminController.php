<?php

namespace App\Controller\Admin;

use App\Service\VimeoService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class VimeoAdminController extends AbstractController
{
    public function __construct(
        private readonly VimeoService $vimeoService,
        private readonly LoggerInterface $logger
    ) {}

    #[Route('/admin/vimeo/upload', name: 'admin_vimeo_upload', methods: ['POST'])]
    public function uploadVideo(Request $request): JsonResponse
    {
        $file = $request->files->get('video');
        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], 400);
        }

        $this->logger->info('Vimeo upload request received', [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);

        $title = $request->request->get('title', 'Uploaded video');
        $description = $request->request->get('description', '');

        try {
            $result = $this->vimeoService->uploadVideo($file, [
                'title' => $title,
                'description' => $description
            ]);

            if (!$result) {
                $this->logger->error('VimeoService returned null for upload');
                return new JsonResponse(['error' => 'Failed to upload to Vimeo (no result)'], 500);
            }

            $this->logger->info('Vimeo upload successful', $result);
            return new JsonResponse($result);
        } catch (\Exception $e) {
            $this->logger->error('Vimeo upload exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
