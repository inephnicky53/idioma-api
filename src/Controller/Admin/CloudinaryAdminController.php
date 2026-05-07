<?php

namespace App\Controller\Admin;

use App\Service\CloudinaryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class CloudinaryAdminController extends AbstractController
{
    public function __construct(
        private readonly CloudinaryService $cloudinaryService
    ) {}

    #[Route('/admin/cloudinary/signature', name: 'admin_cloudinary_signature', methods: ['POST'])]
    public function getSignature(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $paramsToSign = $data['params'] ?? [];
        
        // Paramètres obligatoires pour la signature Cloudinary Widget
        $preset = 'idioma_club';
        
        $paramsToSign['timestamp'] = time();
        $paramsToSign['upload_preset'] = $preset;
        $paramsToSign['source'] = 'uw'; // Requis par le widget Cloudinary (Upload Widget)
        
        $signatureData = $this->cloudinaryService->generateSignature($paramsToSign);
        
        // Ajouter les infos de preset pour le frontend
        $signatureData['upload_preset'] = $preset;
        
        return new JsonResponse($signatureData);
    }
}
