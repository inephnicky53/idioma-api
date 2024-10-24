<?php

namespace App\Service\Teacher;

use App\Entity\Disponibility;
use App\Entity\SpokenLanguage;
use App\Entity\Teacher;
use App\Entity\TeacherCertification;
use App\Entity\TeacherFormation;
use App\Entity\UserTeacher;
use App\Event\TeacherCreatedEvent;
use App\Event\TeacherValidatedEvent;
use App\Model\CreateTeacherModel;
use App\Repository\CurrencyRepository;
use App\Repository\LanguageRepository;
use App\Repository\UserTeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;

class TeacherManager
{
    public function __construct(
        private readonly EntityManagerInterface  $em,
        private readonly LanguageRepository      $languageRepository,
        private readonly CurrencyRepository      $currencyRepository,
        private readonly UserTeacherRepository $userTeacherRepository,
        private readonly Security                $security,
        private readonly EventDispatcherInterface $dispatcher
    )
    {
    }

    /**
     * @throws Exception
     */
    public function create(CreateTeacherModel $model): Teacher
    {
        $language = $this->languageRepository->findOneBy(['locale' => $model->language]);
        if (is_null($language))
            throw new Exception("La langue sélectionnée n'existe pas");

        $currency = $this->currencyRepository->findOneBy(['min' => $model->currency]);
        if (is_null($currency))
            throw new Exception("La devise sélectionnée n'existe pas");

        $user = $this->security->getUser();

        $teacher = (new Teacher())
            ->setUser($user)
            ->setPrice($model->price)
            ->setCurrency($currency)
            ->setShortDescription($model->shortDescription)
            ->setDescription($model->description)
            ->setMotivation($model->motivation)
            ->setExperience($model->experience)
            ->setLanguage($language)
            ->setLink($model->link)
            ->setVideo($model->video)
            ->setProfile($model->profile)
            ->setTimezone($model->timezone)
            ->setStatus(Teacher::STATUS_WAITING)
            ->setIsActive(false);

        foreach ($model->spokenLanguages as $item) {
            $lang = $this->languageRepository->findOneBy(['locale' => $item->language]);

            if (is_null($lang))
                throw new Exception("La langue sélectionnée n'existe pas");

            $teacher->addSpokenLanguage(
                (new SpokenLanguage())
                    ->setLanguage($lang)
                    ->setLevel($item->level)
            );
        }

        foreach ($model->certifications as $item) {
            $lang = $this->languageRepository->findOneBy(['locale' => $item->language]);
            if (is_null($lang))
                throw new Exception("La langue sélectionnée n'existe pas");

            $teacher->addTeacherCertification(
                (new TeacherCertification())
                    ->addLanguage($language)
                    ->setCertification($item->certification)
                    ->setYearStart($item->yearStart)
                    ->setYearEnd($item->yearEnd)
            );
        }

        foreach ($model->formations as $item) {
            $teacher->addTeacherFormation(
                (new TeacherFormation())
                    ->setCertificate($item->certificate)
                    ->setUniversity($item->university)
                    ->setSpeciality($item->speciality)
                    ->setType($item->type)
                    ->setYearStart($item->yearStart)
                    ->setYearEnd($item->yearEnd)
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

    public function save(Teacher $teacher): Teacher
    {
        $user = $this->security->getUser();

        if ($this->userTeacherRepository->findOneBy(['user' => $user, 'teacher' => $teacher]))
            throw new Exception("Ce professeur est déjà ajouté");

        $userTeacher = new UserTeacher();
        $userTeacher
            ->setTeacher($teacher)
            ->setUser($user);

        $this->em->persist($userTeacher);
        $this->em->flush();

        return $teacher;
    }

    public function unsaved(Teacher $data): Teacher
    {
        $user = $this->security->getUser();
        $teacher = $this->userTeacherRepository->findOneBy(['user' => $user, 'teacher' => $data->getId()]);

        if (is_null($teacher))
            throw new Exception("Ce professeur est déjà ajouté");

        if ($teacher) {
            if ($teacher->getHours() > 0)
                throw new Exception("Vous avez des heures disponible pour ce professeur");
        } else {
            throw new Exception("Ce professeur ne fait pas partie de vos favoris");
        }

        $this->em->remove($teacher);
        $this->em->flush();

        return $data;
    }
}