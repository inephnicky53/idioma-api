<?php

namespace App\Service\Course;

use App\Entity\Course;
use App\Entity\CourseLesson;
use App\Entity\CourseSection;
use App\Entity\Language;
use App\Entity\Teacher;
use App\Idioma;
use App\Service\Media\VimeoUrl;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TeacherRecordedCourseService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTeacher(Teacher $teacher): array
    {
        $out = [];
        foreach ($teacher->getCourses() as $course) {
            $out[] = $this->serialize($course, true);
        }

        return $out;
    }

    public function getOwned(Teacher $teacher, int $id): Course
    {
        $course = $this->em->getRepository(Course::class)->find($id);
        if (!$course instanceof Course || $course->getTeacher()?->getId() !== $teacher->getId()) {
            throw new NotFoundHttpException('Cours introuvable.');
        }

        return $course;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(Teacher $teacher, array $payload, ?Course $existing = null): Course
    {
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            throw new BadRequestHttpException('Le titre du cours est requis.');
        }

        $description = trim((string) ($payload['description'] ?? ''));
        if ($description === '') {
            throw new BadRequestHttpException('La description du cours est requise.');
        }

        $languageId = (int) ($payload['languageId'] ?? $payload['language'] ?? 0);
        $language = $languageId > 0
            ? $this->em->getRepository(Language::class)->find($languageId)
            : $teacher->getLanguage();
        if (!$language instanceof Language) {
            throw new BadRequestHttpException('Choisissez la langue du cours.');
        }

        $course = $existing ?? (new Course());
        if (!$existing) {
            $course->setTeacher($teacher);
        } elseif ($course->getTeacher()?->getId() !== $teacher->getId()) {
            throw new AccessDeniedHttpException('Ce cours ne vous appartient pas.');
        }

        $status = (string) ($payload['status'] ?? self::STATUS_DRAFT);
        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED], true)) {
            $status = self::STATUS_DRAFT;
        }

        $difficulty = (string) ($payload['difficulty'] ?? Course::DIFFICULTY_NORMAL);
        $allowedDiff = array_values(Course::getDifficulties());
        if (!in_array($difficulty, $allowedDiff, true)) {
            $difficulty = Course::DIFFICULTY_NORMAL;
        }

        $level = (string) ($payload['level'] ?? Idioma::LEVEL_A1);
        $short = trim((string) ($payload['shortDescription'] ?? ''));
        if ($short === '') {
            $short = mb_substr(strip_tags($description), 0, 180);
        }

        $course
            ->setTitle($title)
            ->setDescription($description)
            ->setShortDescription($short)
            ->setLanguage($language)
            ->setStatus($status)
            ->setDifficulty($difficulty)
            ->setLevel($level)
            ->setAmount((float) ($payload['amount'] ?? 0))
            ->setIsPaid(((float) ($payload['amount'] ?? 0)) > 0)
            ->setCurrency($teacher->getCurrency() ?? $course->getCurrency());

        $this->syncCurriculum($course, is_array($payload['sections'] ?? null) ? $payload['sections'] : []);

        $minutes = 0;
        foreach ($course->getSections() as $section) {
            foreach ($section->getLessons() as $lesson) {
                $minutes += (int) $lesson->getDurationMinutes();
            }
        }
        $course->setDuration(max(1, (int) ceil($minutes / 60)));

        $this->em->persist($course);
        $this->em->flush();

        return $course;
    }

    public function delete(Teacher $teacher, Course $course): void
    {
        if ($course->getTeacher()?->getId() !== $teacher->getId()) {
            throw new AccessDeniedHttpException('Ce cours ne vous appartient pas.');
        }

        $this->em->remove($course);
        $this->em->flush();
    }

    /**
     * @param list<array<string, mixed>> $sectionsPayload
     */
    private function syncCurriculum(Course $course, array $sectionsPayload): void
    {
        $keepSectionIds = [];
        $position = 0;
        foreach ($sectionsPayload as $sectionData) {
            if (!is_array($sectionData)) {
                continue;
            }
            $title = trim((string) ($sectionData['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $section = null;
            $sectionId = (int) ($sectionData['id'] ?? 0);
            if ($sectionId > 0) {
                foreach ($course->getSections() as $existing) {
                    if ($existing->getId() === $sectionId) {
                        $section = $existing;
                        break;
                    }
                }
            }
            if (!$section) {
                $section = new CourseSection();
                $course->addSection($section);
            }

            $section->setTitle($title);
            $section->setPosition($position++);
            $this->syncLessons($section, is_array($sectionData['lessons'] ?? null) ? $sectionData['lessons'] : []);
            $keepSectionIds[] = $section->getId();
        }

        foreach ($course->getSections()->toArray() as $section) {
            $isKept = $section->getId() === null || in_array($section->getId(), array_filter($keepSectionIds), true);
            if (!$isKept) {
                $course->removeSection($section);
                $this->em->remove($section);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $lessonsPayload
     */
    private function syncLessons(CourseSection $section, array $lessonsPayload): void
    {
        $keepIds = [];
        $position = 0;
        foreach ($lessonsPayload as $lessonData) {
            if (!is_array($lessonData)) {
                continue;
            }
            $title = trim((string) ($lessonData['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $lesson = null;
            $lessonId = (int) ($lessonData['id'] ?? 0);
            if ($lessonId > 0) {
                foreach ($section->getLessons() as $existing) {
                    if ($existing->getId() === $lessonId) {
                        $lesson = $existing;
                        break;
                    }
                }
            }
            if (!$lesson) {
                $lesson = new CourseLesson();
                $section->addLesson($lesson);
            }

            $vimeo = trim((string) ($lessonData['vimeoUrl'] ?? ''));
            if ($vimeo !== '' && !VimeoUrl::videoId($vimeo)) {
                throw new BadRequestHttpException("URL Vimeo invalide pour « {$title} ».");
            }

            $type = (string) ($lessonData['type'] ?? CourseLesson::TYPE_VIDEO);
            if (!in_array($type, [CourseLesson::TYPE_VIDEO, CourseLesson::TYPE_ARTICLE, CourseLesson::TYPE_QUIZ], true)) {
                $type = CourseLesson::TYPE_VIDEO;
            }

            $lesson
                ->setTitle($title)
                ->setType($type)
                ->setVimeoUrl($vimeo !== '' ? $vimeo : null)
                ->setDurationMinutes(max(0, (int) ($lessonData['durationMinutes'] ?? 0)))
                ->setIsPreview((bool) ($lessonData['isPreview'] ?? false))
                ->setPosition($position++);

            $keepIds[] = $lesson->getId();
        }

        foreach ($section->getLessons()->toArray() as $lesson) {
            $isKept = $lesson->getId() === null || in_array($lesson->getId(), array_filter($keepIds), true);
            if (!$isKept) {
                $section->removeLesson($lesson);
                $this->em->remove($lesson);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Course $course, bool $includeVimeo): array
    {
        $sections = [];
        foreach ($course->getSections() as $section) {
            $lessons = [];
            foreach ($section->getLessons() as $lesson) {
                $row = [
                    'id' => $lesson->getId(),
                    'title' => $lesson->getTitle(),
                    'type' => $lesson->getType(),
                    'durationMinutes' => $lesson->getDurationMinutes(),
                    'position' => $lesson->getPosition(),
                    'isPreview' => $lesson->isIsPreview(),
                    'hasVimeo' => $lesson->hasVimeo(),
                ];
                if ($includeVimeo) {
                    $row['vimeoUrl'] = $lesson->getVimeoUrl();
                }
                $lessons[] = $row;
            }
            $sections[] = [
                'id' => $section->getId(),
                'title' => $section->getTitle(),
                'position' => $section->getPosition(),
                'lessons' => $lessons,
            ];
        }

        return [
            'id' => $course->getId(),
            'title' => $course->getTitle(),
            'description' => $course->getDescription(),
            'shortDescription' => $course->getShortDescription(),
            'status' => $course->getStatus(),
            'difficulty' => $course->getDifficulty(),
            'level' => $course->getLevel(),
            'duration' => $course->getDuration(),
            'amount' => $course->getAmount(),
            'language' => $course->getLanguage() ? [
                'id' => $course->getLanguage()->getId(),
                'name' => $course->getLanguage()->getName(),
            ] : null,
            'sections' => $sections,
            'lessonsCount' => $course->getLessonsCount(),
        ];
    }
}
