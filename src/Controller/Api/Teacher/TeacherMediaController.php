<?php

namespace App\Controller\Api\Teacher;

use App\Entity\Teacher;
use App\Entity\User;
use App\Service\Teacher\TeacherBecomeUploadService;
use App\Service\Teacher\TeacherManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TeacherMediaController extends AbstractController
{
    public function __construct(
        private readonly TeacherBecomeUploadService $uploads,
        private readonly EntityManagerInterface $em,
        private readonly TeacherManager $teacherManager,
    ) {
    }

    public function __invoke(Request $request): Teacher
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacher = $user->getTeacher();
        if (!$teacher instanceof Teacher) {
            throw new BadRequestHttpException('Vous devez être idiomaster pour continuer');
        }

        $contentLength = (int) $request->headers->get('Content-Length', '0');
        if ($contentLength > 0 && $request->files->count() === 0 && $request->request->count() === 0) {
            throw new BadRequestHttpException(
                'Fichier trop volumineux pour le serveur. Vidéo max 100 Mo, photo ou vignette max 5 Mo.'
            );
        }

        $photo = $this->uploaded($request, 'photo');
        $video = $this->uploaded($request, 'video');
        $thumbnail = $this->uploaded($request, 'thumbnail');
        $link = $request->request->get('link', $request->query->get('link'));
        $hasLink = is_string($link) && trim($link) !== '';

        if ($video && $hasLink) {
            throw new BadRequestHttpException('You must send video or link property, not both');
        }

        $changed = false;

        if ($photo) {
            $path = $this->uploads->store($photo, 'photos');
            $teacher->setProfile($path);
            $owner = $teacher->getUser();
            if ($owner) {
                $owner->setProfile($path);
            }
            $changed = true;
        }

        if ($video) {
            $teacher->setVideo($this->uploads->store($video, 'videos'));
            $teacher->setLink(null);
            $changed = true;
        } elseif ($hasLink && trim($link) !== (string) $teacher->getLink()) {
            $teacher->setLink(trim($link));
            $teacher->setVideo(null);
            $changed = true;
        }

        if ($thumbnail) {
            $teacher->setVideoPoster($this->uploads->store($thumbnail, 'thumbnails'));
            $changed = true;
        }

        $teacher->setStep(3);

        if ($changed) {
            $this->teacherManager->markForAdminReview($teacher);
        } else {
            $this->em->persist($teacher);
            $this->em->flush();
        }

        return $teacher;
    }

    private function uploaded(Request $request, string $field): ?UploadedFile
    {
        $file = $request->files->get($field);
        if (!$file instanceof UploadedFile) {
            return null;
        }
        if (!$file->isValid()) {
            throw new BadRequestHttpException(
                sprintf('Le fichier « %s » est invalide : %s', $field, $file->getErrorMessage())
            );
        }

        return $file;
    }
}
