<?php

namespace App\Controller\Api\Teacher;

use App\Entity\Attachment;
use App\Entity\Teacher;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TeacherMediaController extends AbstractController
{
    public function __invoke(Request $request): JsonResponse|Teacher
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$data = $user->getTeacher())
            return $this->json([
                'status' => false,
                "message" => "Vous devez être idiomaster pour continuer"
            ]);
        $video = $request->files->get('video');
        $link = $request->get('link');

        if (is_null($link) and is_null($video))
            throw new BadRequestHttpException('video or link is required');
        if ($link and $video)
            throw new BadRequestHttpException('You must send video or link property');

        if ($video) {
            if (!$attachment = $data->getVideo()){
                $attachment = new Attachment();
            }
            $attachment->setFile($video);

            $data->setVideo($attachment);
        }

        if ($link) {
            // todo: vérifier si c'est Youtube ou Vimeo
            $data->setLink($link);
        }

        $data->setStep(3);
        return $data;
    }
}
