<?php

namespace App\Controller\Api\Course;

use App\Entity\Course;
use App\Entity\CourseLesson;
use App\Entity\User;
use App\Entity\UserCourse;
use App\Repository\CourseLessonRepository;
use App\Repository\CourseRepository;
use App\Repository\UserCourseRepository;
use App\Service\Media\VimeoUrl;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/courses')]
class CourseLessonWatchController extends AbstractController
{
    public function __construct(
        private readonly CourseRepository $courses,
        private readonly CourseLessonRepository $lessons,
        private readonly UserCourseRepository $userCourses,
    ) {
    }

    #[Route('/{courseId}/lessons/{lessonId}/watch', name: 'api_course_lesson_watch', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function watch(int $courseId, int $lessonId): JsonResponse
    {
        $course = $this->courses->find($courseId);
        $lesson = $lessonId > 0 ? $this->lessons->find($lessonId) : null;

        if (!$course instanceof Course) {
            return $this->json(['error' => 'Cours introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (!$lesson instanceof CourseLesson) {
            $lesson = $this->firstLesson($course);
        }

        if (!$lesson instanceof CourseLesson) {
            return $this->json(['error' => 'Aucune leçon disponible.'], Response::HTTP_NOT_FOUND);
        }

        if ($lesson->getSection()?->getCourse()?->getId() !== $course->getId()) {
            return $this->json(['error' => 'Cette leçon n\'appartient pas à ce cours.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$this->canWatch($user, $course, $lesson)) {
            return $this->json(['error' => 'Vous n\'avez pas accès à cette leçon.'], Response::HTTP_FORBIDDEN);
        }

        $embed = VimeoUrl::toEmbed($lesson->getVimeoUrl());
        if ($lesson->getType() === CourseLesson::TYPE_VIDEO && !$embed) {
            return $this->json(['error' => 'Aucune vidéo Vimeo n\'est liée à cette leçon.'], Response::HTTP_NOT_FOUND);
        }

        $sections = [];
        foreach ($course->getSections() as $section) {
            $items = [];
            foreach ($section->getLessons() as $item) {
                $items[] = [
                    'id' => $item->getId(),
                    'title' => $item->getTitle(),
                    'type' => $item->getType(),
                    'durationMinutes' => $item->getDurationMinutes(),
                    'isPreview' => $item->isIsPreview(),
                    'hasVimeo' => $item->hasVimeo(),
                ];
            }
            $sections[] = [
                'id' => $section->getId(),
                'title' => $section->getTitle(),
                'lessons' => $items,
            ];
        }

        return $this->json([
            'courseId' => $course->getId(),
            'courseTitle' => $course->getTitle(),
            'lessonId' => $lesson->getId(),
            'title' => $lesson->getTitle(),
            'type' => $lesson->getType(),
            'durationMinutes' => $lesson->getDurationMinutes(),
            'isPreview' => $lesson->isIsPreview(),
            'embedUrl' => $embed,
            'vimeoId' => VimeoUrl::videoId($lesson->getVimeoUrl()),
            'sections' => $sections,
        ]);
    }

    private function canWatch(User $user, Course $course, CourseLesson $lesson): bool
    {
        if ($lesson->isIsPreview()) {
            return true;
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($course->getTeacher()?->getUser() === $user) {
            return true;
        }

        $enrollment = $this->userCourses->findOneBy([
            'user' => $user,
            'course' => $course,
        ]);

        return $enrollment instanceof UserCourse && $enrollment->isIsBuyed();
    }

    private function firstLesson(Course $course): ?CourseLesson
    {
        foreach ($course->getSections() as $section) {
            foreach ($section->getLessons() as $lesson) {
                return $lesson;
            }
        }

        return null;
    }
}
