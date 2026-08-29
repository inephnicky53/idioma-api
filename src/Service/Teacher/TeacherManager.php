<?php

namespace App\Service\Teacher;

use App\Dto\UpdateTeacherInput;
use App\Entity\Disponibility;
use App\Entity\SpokenLanguage;
use App\Entity\Teacher;
use App\Entity\TeacherCertification;
use App\Entity\TeacherFormation;
use App\Entity\TeachingLanguage;
use App\Entity\User;
use App\Entity\UserTeacher;
use App\Event\TeacherCreatedEvent;
use App\Event\TeacherValidatedEvent;
use App\Model\CreateTeacherModel;
use App\Repository\CurrencyRepository;
use App\Repository\LanguageRepository;
use App\Repository\UserTeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class TeacherManager
{
    public function __construct(
        private EntityManagerInterface   $em,
        private LanguageRepository       $languageRepository,
        private CurrencyRepository       $currencyRepository,
        private UserTeacherRepository    $userTeacherRepository,
        private Security                 $security,
        private EventDispatcherInterface $dispatcher
    ) {}

    /**
     * @throws Exception
     */
    public function create(CreateTeacherModel $model): Teacher
    {
        $language = $this->languageRepository->findOneBy(['locale' => $model->language]);
        if (is_null($language))
            throw new InvalidArgumentException("La langue sélectionnée n'existe pas");

        $currency = $this->currencyRepository->findOneBy(['min' => $model->currency]);
        if (is_null($currency))
            throw new InvalidArgumentException("La devise sélectionnée n'existe pas");

        /** @var User $user */
        $user = $this->security->getUser();

        if ($model->firstname) {
            $user->setFirstname($model->firstname);
        }
        if ($model->lastname) {
            $user->setName($model->lastname);
        }
        if ($model->phone) {
            $user->setPhone($model->phone);
        }

        $teacher = (new Teacher())
            ->setPrice($model->price)
            ->setCurrency($currency)
            ->setShortDescription($model->shortDescription)
            ->setDescription($model->description)
            ->setMotivation($model->motivation)
            ->setExperience($model->experience)
            ->setLanguage($language)
            ->setLink($model->link)
            ->setVideo($model->video)
            ->setVideoPoster($model->videoPoster)
            ->setProfile($model->profile)
            ->setTimezone($model->timezone)
            ->setStatus(Teacher::STATUS_WAITING)
            ->setIsActive(false);

        foreach ($model->spokenLanguages as $item) {
            $teacher->addSpokenLanguage(
                (new SpokenLanguage())
                    ->setLanguage($item->language)
                    ->setLevel($item->level)
            );
        }

        foreach ($model->languages as $item) {
            $lang = $this->languageRepository->findOneBy(['locale' => $item->language]);

            if (is_null($lang))
                throw new InvalidArgumentException("La langue sélectionnée n'existe pas");

            $teacher->addTeachingLanguage(
                (new TeachingLanguage())
                    ->setLanguage($lang)
            );
        }

        foreach ($model->certifications as $item) {
            $lang = $item->language
                ? $this->languageRepository->findOneBy(['locale' => $item->language])
                : null;

            // Skip incomplete/invalid certifications instead of failing the whole
            // become-teacher request.
            if (is_null($lang))
                continue;

            $teacher->addTeacherCertification(
                (new TeacherCertification())
                    ->setCertification($item->certification)
                    ->addLanguage($lang)
                    ->setYearStart($item->yearStart)
                    ->setYearEnd($item->yearEnd)
                    ->setProofImage($item->proofImage)
            );
        }

        foreach ($model->formations as $item) {
            $teacher->addTeacherFormation(
                (new TeacherFormation())
                    ->setCertificate($item->certificate)
                    ->setUniversity($item->university)
                    ->setSpeciality($item->speciality)
                    ->setYearStart($item->yearStart !== null && $item->yearStart !== '' ? (int) $item->yearStart : null)
                    ->setYearEnd($item->yearEnd !== null && $item->yearEnd !== '' ? (int) $item->yearEnd : null)
                    ->setProofImage($item->proofImage)
            );
        }

        foreach ($model->availabilities as $item) {
            array_map(function ($program) use ($item, &$teacher) {
                $teacher->addDisponibility(
                    (new Disponibility())
                        ->setDay($item->day)
                        ->setStart($program->start)
                        ->setEnd($program->end)
                        ->setIsActive(true)
                );
            }, $item->programs);
        }


        $user->setTeacher($teacher);

        $this->em->persist($teacher);
        $this->em->flush();

        $this->dispatcher->dispatch(new TeacherCreatedEvent($teacher));

        return $teacher;
    }

    public function validate(Teacher $teacher): Teacher
    {
        $teacher->setIsActive(true);
        $teacher->setStatus(Teacher::STATUS_VALIDATED);
        $teacher->setIsVerified(true);
        $teacher->setVerifiedAt(new \DateTimeImmutable());
        $teacher->setVerifiedBy($this->security->getUser());

        $user = $teacher->getUser();
        if ($user instanceof User && !in_array('ROLE_TEACHER', $user->getRoles(), true)) {
            $user->setRoles([...$user->getRoles(), 'ROLE_TEACHER']);
        }

        $this->em->persist($teacher);
        $this->em->flush();

        $this->dispatcher->dispatch(new TeacherValidatedEvent($teacher));

        return $teacher;
    }

    public function invalidate(Teacher $teacher): Teacher
    {
        $teacher->setIsActive(false);
        $teacher->setStatus(Teacher::STATUS_WAITING);
        $teacher->setIsVerified(false);
        $teacher->setVerifiedAt(null);
        $teacher->setVerifiedBy(null);

        $this->em->persist($teacher);
        $this->em->flush();

        return $teacher;
    }

    public function updateAvailabilities(Teacher $teacher, array $availabilities): Teacher
    {
        foreach ($teacher->getDisponibilities()->toArray() as $disponibility) {
            $teacher->removeDisponibility($disponibility);
        }

        foreach ($availabilities as $availabilityModel) {
            foreach ($availabilityModel->programs as $timeSlot) {
                $teacher->addDisponibility(
                    (new Disponibility())
                        ->setDay(ucfirst($availabilityModel->day))
                        ->setStart($timeSlot->start)
                        ->setEnd($timeSlot->end)
                        ->setIsActive(true)
                );
            }
        }

        $this->em->flush();

        return $teacher;
    }

    /**
     * @throws Exception
     */
    public function save(Teacher $teacher): Teacher
    {
        $user = $this->security->getUser();

        if ($this->userTeacherRepository->findOneBy(['user' => $user, 'teacher' => $teacher]))
            throw new Exception("Cet idiomaster est déjà ajouté");

        $userTeacher = new UserTeacher();
        $userTeacher
            ->setTeacher($teacher)
            ->setUser($user);

        $this->em->persist($userTeacher);
        $this->em->flush();

        return $teacher;
    }

    /**
     * @throws Exception
     */
    public function unsaved(Teacher $data): Teacher
    {
        $user = $this->security->getUser();
        $teacher = $this->userTeacherRepository->findOneBy(['user' => $user, 'teacher' => $data->getId()]);

        if (is_null($teacher))
            throw new Exception("Cet idiomaster est déjà ajouté");

        if ($teacher) {
            if ($teacher->getHours() > 0)
                throw new Exception("Vous avez des heures disponible pour cet idiomaster");
        } else {
            throw new Exception("Cet idiomaster ne fait pas partie de vos favoris");
        }

        $this->em->remove($teacher);
        $this->em->flush();

        return $data;
    }

    /**
     * When a published profile changes, send it back to admin validation queue.
     */
    public function markForAdminReview(Teacher $teacher): Teacher
    {
        $teacher->setSubmitedAt(new \DateTimeImmutable());

        $wasPublished = $teacher->isIsActive()
            || (int) $teacher->getStatus() === Teacher::STATUS_VALIDATED;

        if ($wasPublished) {
            $this->invalidate($teacher);
        }

        $this->em->persist($teacher);
        $this->em->flush();

        return $teacher;
    }

    /**
     * Full profile resubmission for an existing teacher (same payload as become flow).
     */
    public function updateFromBecomeInput(Teacher $teacher, \App\Dto\CreateTeacherInput $input): Teacher
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $needsReview = false;

        if ($input->firstname && $input->firstname !== $user->getFirstname()) {
            $user->setFirstname($input->firstname);
            $needsReview = true;
        }
        if ($input->lastname && $input->lastname !== $user->getName()) {
            $user->setName($input->lastname);
            $needsReview = true;
        }
        if ($input->phone && $input->phone !== $user->getPhone()) {
            $user->setPhone($input->phone);
            $needsReview = true;
        }

        if ($input->language) {
            $language = $this->languageRepository->findOneBy(['locale' => $input->language]);
            if ($language && $teacher->getLanguage()?->getId() !== $language->getId()) {
                $teacher->setLanguage($language);
                $needsReview = true;
            }
        }

        if ($input->price !== null && (float) $input->price !== (float) $teacher->getPrice()) {
            $teacher->setPrice($input->price);
            $needsReview = true;
        }

        if ($input->currency) {
            $currency = $this->currencyRepository->findOneBy(['min' => $input->currency]);
            if ($currency) {
                $teacher->setCurrency($currency);
            }
        }

        if ($input->profile && $input->profile !== $teacher->getProfile()) {
            $teacher->setProfile($input->profile);
            $user->setProfile($input->profile);
            $needsReview = true;
        }
        if ($input->video && $input->video !== $teacher->getVideo()) {
            $teacher->setVideo($input->video);
            $teacher->setLink(null);
            $needsReview = true;
        } elseif ($input->link !== null && $input->link !== $teacher->getLink()) {
            $teacher->setLink($input->link ?: null);
            if ($input->link) {
                $teacher->setVideo(null);
            }
            $needsReview = true;
        }
        if ($input->videoPoster && $input->videoPoster !== $teacher->getVideoPoster()) {
            $teacher->setVideoPoster($input->videoPoster);
            $needsReview = true;
        }
        if ($input->shortDescription !== null && $input->shortDescription !== $teacher->getShortDescription()) {
            $teacher->setShortDescription($input->shortDescription);
            $needsReview = true;
        }
        if ($input->description !== null && $input->description !== $teacher->getDescription()) {
            $teacher->setDescription($input->description);
            $needsReview = true;
        }
        if ($input->experience !== null && $input->experience !== $teacher->getExperience()) {
            $teacher->setExperience($input->experience);
            $needsReview = true;
        }
        if ($input->motivation !== null && $input->motivation !== $teacher->getMotivation()) {
            $teacher->setMotivation($input->motivation);
            $needsReview = true;
        }
        if ($input->timezone && $input->timezone !== $teacher->getTimezone()) {
            $teacher->setTimezone($input->timezone);
        }

        if ($input->spokenLanguages) {
            foreach ($teacher->getSpokenLanguages()->toArray() as $spokenLanguage) {
                $teacher->removeSpokenLanguage($spokenLanguage);
            }
            foreach ($input->spokenLanguages as $item) {
                $teacher->addSpokenLanguage(
                    (new SpokenLanguage())
                        ->setLanguage($item->language)
                        ->setLevel($item->level)
                );
            }
            $needsReview = true;
        }

        if ($input->languages) {
            foreach ($teacher->getTeachingLanguages()->toArray() as $teachingLanguage) {
                $teacher->removeTeachingLanguage($teachingLanguage);
            }
            foreach ($input->languages as $item) {
                $lang = $this->languageRepository->findOneBy(['locale' => $item->language]);
                if ($lang) {
                    $teacher->addTeachingLanguage((new TeachingLanguage())->setLanguage($lang));
                }
            }
            $needsReview = true;
        }

        if ($input->certifications !== []) {
            foreach ($teacher->getTeacherCertifications()->toArray() as $cert) {
                $teacher->removeTeacherCertification($cert);
            }
            foreach ($input->certifications as $item) {
                $lang = $item->language
                    ? $this->languageRepository->findOneBy(['locale' => $item->language])
                    : null;
                if (!$lang) {
                    continue;
                }
                $teacher->addTeacherCertification(
                    (new TeacherCertification())
                        ->setCertification($item->certification)
                        ->addLanguage($lang)
                        ->setYearStart($item->yearStart)
                        ->setYearEnd($item->yearEnd)
                        ->setProofImage($item->proofImage)
                );
            }
            $needsReview = true;
        }

        if ($input->formations !== []) {
            foreach ($teacher->getTeacherFormations()->toArray() as $formation) {
                $teacher->removeTeacherFormation($formation);
            }
            foreach ($input->formations as $item) {
                $teacher->addTeacherFormation(
                    (new TeacherFormation())
                        ->setCertificate($item->certificate)
                        ->setUniversity($item->university)
                        ->setSpeciality($item->speciality)
                        ->setYearStart($item->yearStart !== null && $item->yearStart !== '' ? (int) $item->yearStart : null)
                        ->setYearEnd($item->yearEnd !== null && $item->yearEnd !== '' ? (int) $item->yearEnd : null)
                        ->setProofImage($item->proofImage)
                );
            }
            $needsReview = true;
        }

        if ($input->availabilities) {
            $this->updateAvailabilities($teacher, $input->availabilities);
        }

        if ($needsReview) {
            $this->markForAdminReview($teacher);
        } else {
            $this->em->persist($teacher);
            $this->em->persist($user);
            $this->em->flush();
        }

        return $teacher;
    }

    /**
     * @throws Exception
     */
    public function update(UpdateTeacherInput $updateData): Teacher
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $teacher = $this->em->getRepository(Teacher::class)->findOneBy(['user' => $user]);
        if (!$teacher)
            throw new Exception('User is not a teacher');

        $needsReview = false;

        if (isset($updateData->shortDescription)) {
            if ($updateData->shortDescription !== $teacher->getShortDescription()) {
                $needsReview = true;
            }
            $teacher->setShortDescription($updateData->shortDescription);
        }

        if (isset($updateData->description)) {
            if ($updateData->description !== $teacher->getDescription()) {
                $needsReview = true;
            }
            $teacher->setDescription($updateData->description);
        }

        if (isset($updateData->experience)) {
            if ($updateData->experience !== $teacher->getExperience()) {
                $needsReview = true;
            }
            $teacher->setExperience($updateData->experience);
        }

        if (isset($updateData->motivation)) {
            if ($updateData->motivation !== $teacher->getMotivation()) {
                $needsReview = true;
            }
            $teacher->setMotivation($updateData->motivation);
        }

        if (isset($updateData->timezone)) {
            $teacher->setTimezone($updateData->timezone);
        }

        if (isset($updateData->profile)) {
            if ($updateData->profile !== $teacher->getProfile()) {
                $needsReview = true;
            }
            $teacher->setProfile($updateData->profile);
        }

        if (isset($updateData->price)) {
            if ((float) $updateData->price !== (float) $teacher->getPrice()) {
                $needsReview = true;
            }
            $teacher->setPrice($updateData->price);
        }

        if (isset($updateData->spokenLanguages) && is_array($updateData->spokenLanguages)) {
            $previous = $this->normalizeSpokenLanguages($teacher);
            $next = $this->normalizeSpokenLanguagesInput($updateData->spokenLanguages);
            if ($previous !== $next) {
                $needsReview = true;
            }

            foreach ($teacher->getSpokenLanguages() as $spokenLanguage) {
                $teacher->removeSpokenLanguage($spokenLanguage);
            }

            foreach ($updateData->spokenLanguages as $item) {
                $locale = is_object($item) ? ($item->language ?? null) : ($item['language'] ?? null);
                $level = is_object($item) ? ($item->level ?? null) : ($item['level'] ?? null);
                $lang = $locale ? $this->languageRepository->findOneBy(['locale' => $locale]) : null;
                if ($lang && $level) {
                    $teacher->addSpokenLanguage(
                        (new SpokenLanguage())
                            ->setLanguage($lang->getLocale())
                            ->setLevel($level)
                    );
                }
            }
        }

        if ($needsReview) {
            return $this->markForAdminReview($teacher);
        }

        $this->em->persist($teacher);
        $this->em->flush();

        return $teacher;
    }

    /** @return list<array{language: string, level: string|null}> */
    private function normalizeSpokenLanguages(Teacher $teacher): array
    {
        $items = [];
        foreach ($teacher->getSpokenLanguages() as $spokenLanguage) {
            $items[] = [
                'language' => (string) $spokenLanguage->getLanguage(),
                'level' => $spokenLanguage->getLevel(),
            ];
        }
        usort($items, fn(array $a, array $b) => strcmp($a['language'], $b['language']));

        return $items;
    }

    /** @return list<array{language: string, level: string|null}> */
    private function normalizeSpokenLanguagesInput(array $input): array
    {
        $items = [];
        foreach ($input as $item) {
            $locale = is_object($item) ? ($item->language ?? null) : ($item['language'] ?? null);
            $level = is_object($item) ? ($item->level ?? null) : ($item['level'] ?? null);
            if ($locale) {
                $items[] = ['language' => (string) $locale, 'level' => $level];
            }
        }
        usort($items, fn(array $a, array $b) => strcmp($a['language'], $b['language']));

        return $items;
    }
}
