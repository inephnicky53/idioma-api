<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Course;
use App\Entity\CourseLesson;
use App\Entity\CourseSection;
use App\Entity\Currency;
use App\Entity\Disponibility;
use App\Entity\Language;
use App\Entity\Planning;
use App\Entity\Rating;
use App\Entity\SpokenLanguage;
use App\Entity\Teacher;
use App\Entity\TeachingLanguage;
use App\Entity\User;
use App\Entity\UserCourse;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private const PASSWORD = 'Password123!';

    private Generator $faker;

    /** @var Language[] */
    private array $languages = [];

    /** @var Currency[] */
    private array $currencies = [];

    /** @var Category[] */
    private array $categories = [];

    private const COURSE_TOPICS = [
        'Conversation courante',
        'Grammaire avancée',
        'Préparation aux examens',
        'Langue des affaires',
        'Cours pour débutants',
        'Perfectionnement à l\'oral',
        'Prononciation et accent',
        'Vocabulaire thématique',
        'Voyage et tourisme',
        'Rédaction et écriture',
    ];

    private const SECTION_TOPICS = [
        'Introduction et objectifs',
        'Les fondamentaux',
        'Mise en pratique',
        'Approfondissement',
        'Cas pratiques',
        'Révisions et évaluation',
    ];

    private const LESSON_TOPICS = [
        'Présentation de la leçon',
        'Vocabulaire clé',
        'Exercice guidé',
        'Dialogue commenté',
        'Point de grammaire',
        'Mise en situation',
        'Quiz de validation',
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher
    ) {
        $this->faker = Factory::create('fr_FR');
    }

    public function load(ObjectManager $manager): void
    {
        $this->createLanguages($manager);
        $this->createCurrencies($manager);
        $this->createCategories($manager);
        $manager->flush();

        $teachers = $this->createTeachers($manager, 13);
        $manager->flush();

        $courses = $this->createCourses($manager, $teachers);
        $manager->flush();

        $students = $this->createStudents($manager, 32);
        $manager->flush();

        $this->createBookingsAndRatings($manager, $teachers, $students);
        $this->createEnrollments($manager, $courses, $students);
        $this->createCourseRatings($manager, $courses, $students);
        $manager->flush();
    }

    private function createLanguages(ObjectManager $manager): void
    {
        $definitions = [
            ['Français', 'fr', 'fr'],
            ['Anglais', 'gb', 'en'],
            ['Espagnol', 'es', 'es'],
            ['Allemand', 'de', 'de'],
            ['Italien', 'it', 'it'],
            ['Portugais', 'pt', 'pt'],
        ];

        foreach ($definitions as [$name, $flag, $locale]) {
            $language = new Language();
            $language->setName($name);
            $language->setFlag($flag);
            $language->setLocale($locale);
            $language->setIsActive(true);
            $language->setIsPublic(true);
            $manager->persist($language);
            $this->languages[] = $language;
        }
    }

    private function createCurrencies(ObjectManager $manager): void
    {
        foreach ([['Dollar américain', 'USD'], ['Euro', 'EUR']] as [$name, $min]) {
            $currency = new Currency();
            $currency->setName($name);
            $currency->setMin($min);
            $manager->persist($currency);
            $this->currencies[] = $currency;
        }
    }

    private function createCategories(ObjectManager $manager): void
    {
        foreach (['Conversation', 'Grammaire', 'Business', 'Examens', 'Voyage'] as $name) {
            $category = new Category();
            $category->setName($name);
            $manager->persist($category);
            $this->categories[] = $category;
        }
    }

    /**
     * @return Teacher[]
     */
    private function createTeachers(ObjectManager $manager, int $count): array
    {
        $teachers = [];

        for ($i = 0; $i < $count; $i++) {
            $user = new User();
            $user->setFirstname($this->faker->firstName());
            $user->setName($this->faker->lastName());
            $user->setEmail($this->faker->unique()->safeEmail());
            $user->setPhone('+243' . $this->faker->unique()->numerify('9########'));
            $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
            $user->setCountry($this->faker->randomElement(['CD', 'FR', 'BE', 'CA', 'SN']));
            $user->setIsActive(true);
            $user->setIsVerified(true);
            $manager->persist($user);

            $mainLanguage = $this->faker->randomElement($this->languages);

            $teacher = new Teacher();
            $teacher->setUser($user);
            $teacher->setLanguage($mainLanguage);
            $teacher->setCurrency($this->faker->randomElement($this->currencies));
            $teacher->setPrice((float) $this->faker->numberBetween(8, 45));
            $teacher->setShortDescription($this->faker->sentence(10));
            $teacher->setDescription($this->faker->paragraphs(3, true));
            $teacher->setExperience($this->faker->sentence(15));
            $teacher->setMotivation($this->faker->sentence(18));
            $teacher->setTimezone($this->faker->randomElement([
                'Europe/Paris', 'Africa/Kinshasa', 'Europe/London', 'America/Montreal',
            ]));
            $teacher->setStatus((string) Teacher::STATUS_VALIDATED);
            $teacher->setIsActive(true);
            $teacher->setIsVerified(true);

            $teacher->addTeachingLanguage(
                (new TeachingLanguage())->setLanguage($mainLanguage)
            );

            foreach ($this->faker->randomElements($this->languages, rand(1, 2)) as $spoken) {
                $teacher->addSpokenLanguage(
                    (new SpokenLanguage())
                        ->setLanguage($spoken->getLocale())
                        ->setLevel($this->faker->randomElement(['Natif', 'C2', 'C1', 'B2']))
                );
            }

            foreach ($this->randomWeeklySlots() as [$day, $start, $end]) {
                $teacher->addDisponibility(
                    (new Disponibility())
                        ->setDay($day)
                        ->setStart($start)
                        ->setEnd($end)
                        ->setIsActive(true)
                );
            }

            $manager->persist($teacher);
            $teachers[] = $teacher;
        }

        return $teachers;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function randomWeeklySlots(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $chosenDays = $this->faker->randomElements($days, rand(3, 5));

        $slots = [];
        foreach ($chosenDays as $day) {
            $startHour = $this->faker->randomElement([8, 9, 10, 14, 15, 16]);
            $slots[] = [$day, sprintf('%02d:00', $startHour), sprintf('%02d:00', $startHour + 3)];
        }

        return $slots;
    }

    /**
     * @param Teacher[] $teachers
     * @return Course[]
     */
    private function createCourses(ObjectManager $manager, array $teachers): array
    {
        $courses = [];

        foreach ($teachers as $teacher) {
            $courseCount = rand(1, 3);

            for ($i = 0; $i < $courseCount; $i++) {
                $topic = $this->faker->randomElement(self::COURSE_TOPICS);
                $language = $teacher->getLanguage();

                $course = new Course();
                $course->setTitle(sprintf('%s : %s', $language?->getName() ?? 'Langue', $topic));
                $course->setShortDescription($this->faker->sentence(12));
                $course->setDescription($this->faker->paragraphs(3, true));
                $course->setStatus('published');
                $course->setDifficulty($this->faker->randomElement([
                    Course::DIFFICULTY_EASY, Course::DIFFICULTY_NORMAL, Course::DIFFICULTY_HARD,
                ]));
                $course->setLevel($this->faker->randomElement(['A1', 'A2', 'B1', 'B2', 'C1']));
                $course->setIsPaid(true);
                $course->setLanguage($language);
                $course->setTeacher($teacher);

                $amount = (float) $this->faker->numberBetween(15, 80);
                $isPromoted = $this->faker->boolean(35);
                $course->setAmount($amount);
                $course->setAmountPromo($isPromoted ? round($amount * $this->faker->randomFloat(2, 0.5, 0.8), 2) : 0.0);
                $course->setIsPromote($isPromoted);
                $course->setCurrency($teacher->getCurrency());

                $course->setIsBestseller($this->faker->boolean(20));
                $course->setIsNew($this->faker->boolean(25));
                $course->setHasCertificate($this->faker->boolean(80));
                $course->setHasLifetimeAccess($this->faker->boolean(90));
                $course->setQuizzesCount($this->faker->numberBetween(0, 5));

                foreach ($this->faker->randomElements($this->categories, rand(1, 2)) as $category) {
                    $course->addCategory($category);
                }

                $this->addCurriculum($course);
                // duration is a manual fallback; getTotalDurationMinutes() prefers the curriculum sum.
                $course->setDuration($this->faker->randomElement([30, 45, 60, 90]));

                $manager->persist($course);
                $courses[] = $course;
            }
        }

        return $courses;
    }

    private function addCurriculum(Course $course): void
    {
        $sectionTopics = $this->faker->randomElements(
            self::SECTION_TOPICS,
            min(count(self::SECTION_TOPICS), rand(3, 5))
        );

        foreach (array_values($sectionTopics) as $sectionIndex => $sectionTitle) {
            $section = (new CourseSection())
                ->setTitle(sprintf('%d. %s', $sectionIndex + 1, $sectionTitle))
                ->setPosition($sectionIndex);

            $lessonCount = rand(2, 5);
            for ($l = 0; $l < $lessonCount; $l++) {
                $lessonTitle = $this->faker->randomElement(self::LESSON_TOPICS);
                $isQuiz = $lessonTitle === 'Quiz de validation';

                $section->addLesson(
                    (new CourseLesson())
                        ->setTitle(sprintf('%d.%d %s', $sectionIndex + 1, $l + 1, $lessonTitle))
                        ->setType($isQuiz ? CourseLesson::TYPE_QUIZ : CourseLesson::TYPE_VIDEO)
                        ->setDurationMinutes($isQuiz ? 0 : $this->faker->numberBetween(4, 18))
                        ->setPosition($l)
                        ->setIsPreview($sectionIndex === 0 && $l === 0)
                );
            }

            $course->addSection($section);
        }
    }

    /**
     * @return User[]
     */
    private function createStudents(ObjectManager $manager, int $count): array
    {
        $students = [];

        for ($i = 0; $i < $count; $i++) {
            $user = new User();
            $user->setFirstname($this->faker->firstName());
            $user->setName($this->faker->lastName());
            $user->setEmail($this->faker->unique()->safeEmail());
            $user->setPhone('+243' . $this->faker->unique()->numerify('8########'));
            $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
            $user->setCountry($this->faker->randomElement(['CD', 'FR', 'BE', 'CA', 'SN', 'MA']));
            $user->setIsActive(true);
            $user->setIsVerified(true);
            $manager->persist($user);
            $students[] = $user;
        }

        return $students;
    }

    /**
     * @param Teacher[] $teachers
     * @param User[] $students
     */
    private function createBookingsAndRatings(ObjectManager $manager, array $teachers, array $students): void
    {
        $now = new DateTimeImmutable();

        foreach ($teachers as $teacher) {
            $bookingCount = rand(2, 5);

            for ($i = 0; $i < $bookingCount; $i++) {
                $student = $this->faker->randomElement($students);
                $isPast = $this->faker->boolean(65);

                $start = $isPast
                    ? $now->modify(sprintf('-%d days', rand(1, 45)))->setTime(rand(8, 18), 0)
                    : $now->modify(sprintf('+%d days', rand(1, 21)))->setTime(rand(8, 18), 0);

                $planning = new Planning();
                $planning->setTeacher($teacher);
                $planning->setStart($start);
                $planning->setEnd($start->modify('+30 minutes'));
                $planning->setStatus($isPast ? Planning::STATUS_FINISHED : Planning::STATUS_CREATED);
                $planning->addParticipant($student);
                $manager->persist($planning);

                if ($isPast && $this->faker->boolean(70)) {
                    $rating = new Rating();
                    $rating->setTeacher($teacher);
                    $rating->setUser($student);
                    $rating->setStars((float) $this->faker->randomElement([3, 3.5, 4, 4.5, 5]));
                    $rating->setComment($this->faker->sentence(rand(8, 20)));
                    $manager->persist($rating);
                }
            }
        }
    }

    /**
     * @param Course[] $courses
     * @param User[] $students
     */
    private function createEnrollments(ObjectManager $manager, array $courses, array $students): void
    {
        $enrollmentCount = min(count($courses) * 2, count($students) * 2);

        for ($i = 0; $i < $enrollmentCount; $i++) {
            $course = $this->faker->randomElement($courses);
            $student = $this->faker->randomElement($students);

            $userCourse = new UserCourse();
            $userCourse->setCourse($course);
            $userCourse->setUser($student);
            $userCourse->setAddedAt(new DateTimeImmutable(sprintf('-%d days', rand(1, 60))));
            $userCourse->setIsBuyed(true);
            $userCourse->setBuyedAt(new DateTimeImmutable(sprintf('-%d days', rand(1, 60))));
            $userCourse->setAmount($course->getAmount() ?? 0);
            $userCourse->setCurrency($course->getCurrency());
            $userCourse->setStatus('active');

            $manager->persist($userCourse);
        }
    }

    /**
     * @param Course[] $courses
     * @param User[] $students
     */
    private function createCourseRatings(ObjectManager $manager, array $courses, array $students): void
    {
        foreach ($courses as $course) {
            $reviewCount = $this->faker->numberBetween(0, 12);
            $reviewers = $this->faker->randomElements($students, min($reviewCount, count($students)));

            foreach ($reviewers as $student) {
                $rating = new Rating();
                $rating->setCourse($course);
                $rating->setUser($student);
                $rating->setStars((float) $this->faker->randomElement([3, 3.5, 4, 4, 4.5, 4.5, 5, 5]));
                $rating->setComment($this->faker->sentence(rand(8, 20)));
                $manager->persist($rating);
            }
        }
    }
}
