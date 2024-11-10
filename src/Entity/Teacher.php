<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model;
use App\Controller\Api\Teacher\TeacherMediaController;
use App\Controller\Api\Teacher\TeacherGetCoursesController;
use App\Dto\CreateTeacherInput;
use App\Idioma;
use App\Repository\TeacherRepository;
use App\State\Teacher\CreateTeacherProcessor;
use App\State\Teacher\SaveTeacherProcessor;
use App\State\Teacher\TeacherDisponibilitiesProvider;
use App\State\Teacher\TeacherCertificationsProcessor;
use App\State\Teacher\TeacherCollectionProvider;
use App\State\Teacher\TeacherCheckProvider;
use App\State\Teacher\TeacherGetProvider;
use App\State\Teacher\UnsavedTeacherProcessor;
use App\Trait\Activable;
use App\Trait\Datable;
use App\Trait\Ratingable;
use App\Trait\Verifiable;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TeacherRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['teacher:list']],
            provider: TeacherCollectionProvider::class
        ),
        new Get(
            provider: TeacherGetProvider::class,
        ),
        new Get(
            uriTemplate: "teacher/check",
            provider: TeacherCheckProvider::class
        ),
        new Get(
            uriTemplate: "teachers/{id}/courses",
            controller: TeacherGetCoursesController::class,
        ),
        new Get(
            uriTemplate: "teachers/{id}/disponibilities",
            normalizationContext: ['groups' => ['teacher:disponibilities:list']],
            provider: TeacherDisponibilitiesProvider::class
        ),
        new Post(
            uriTemplate: "teachers/{id}/favorite",
            security: "is_granted('ROLE_USER')",
            processor: SaveTeacherProcessor::class
        ),
        new Delete(
            uriTemplate: "teachers/{id}/favorite",
            security: "is_granted('ROLE_USER')",
            processor: UnsavedTeacherProcessor::class,
        ),
        new Post(
            uriTemplate: "teachers/become",
            security: "is_granted('ROLE_USER')",
            input: CreateTeacherInput::class,
            processor: CreateTeacherProcessor::class,
        ),
        new Patch(
            denormalizationContext: ['groups' => ['teacher:update']],
            security: "is_granted('ROLE_USER')",
        ),
        new Patch(
            uriTemplate: "teacher/{id}/pricing",
            denormalizationContext: ['groups' => ['teacher:pricing']],
            security: "is_granted('ROLE_USER')",
        ),
        new Patch(
            uriTemplate: "teacher/{id}/certifications",
            denormalizationContext: ['groups' => ['teacher:certifications']],
            security: "is_granted('ROLE_USER')",
            processor: TeacherCertificationsProcessor::class
        ),
        new Post(
            uriTemplate: 'teacher/media',
            controller: TeacherMediaController::class,
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'profile' => [
                                        'type' => 'string',
                                        'format' => 'binary'
                                    ],
                                    'video' => [
                                        'type' => 'string',
                                        'format' => 'binary'
                                    ],
                                    'link' => [
                                        'type' => 'string'
                                    ]
                                ]
                            ]
                        ]
                    ])
                )
            ),
            normalizationContext: ['groups' => ['teacher:list', 'teacher:view']],
            security: "is_granted('ROLE_USER')",
            validationContext: ['groups' => ['teacher:media']],
            deserialize: false
        )
    ],
    normalizationContext: ['groups' => ['teacher:list', 'teacher:show']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'language' => 'exact',
    'teachingLanguages' => 'exact',
    'spokenLanguages' => 'exact',
    'status' => 'exact'
])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive'])]
#[ApiFilter(RangeFilter::class, properties: ['price'])]
class Teacher
{
    const STATUS_WAITING = 0;
    const STATUS_VALIDATED = 1;
    const STATUS_OK = 2;
    const STATUS_APPROVAL = 3;
    const STATUS_DEACTIVATE = 5;
    const STATUS_BLOCKED = 4;

    use Activable {
        Activable::__construct as private activableConstructor;
    }
    use Datable {
        Datable::__construct as private dateConstructor;
    }
    use Ratingable {
        Ratingable::__construct as private ratingableConstructor;
    }
    use Verifiable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['teacher:list', 'course:list', 'user:courses', 'user:teachers', 'planning:show', 'user:inbox', 'order:list'])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'teacher', cascade: ['persist', 'remove'])]
    #[Groups(['teacher:list', 'course:list', 'user:courses', 'user:teachers', 'planning:show', 'user:inbox'])]
    private ?User $user = null;

    #[ORM\ManyToMany(targetEntity: Language::class, inversedBy: 'teachers')]
    #[Groups(['teacher:list', 'user:teachers'])]
    private Collection $languages;

    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'teachers')]
    #[Groups(['teacher:show'])]
    private Collection $categories;

    #[ORM\OneToMany(mappedBy: 'teacher', targetEntity: Planning::class)]
    #[Groups(['teacher:show'])]
    private Collection $plannings;

    #[ORM\OneToMany(mappedBy: 'teacher', targetEntity: Course::class)]
    private Collection $courses;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['teacher:show', 'teacher:become', 'planning:show', 'teacher:update'])]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'teacher', targetEntity: Social::class)]
    #[Groups(['teacher:show'])]
    private Collection $socials;

    #[ORM\OneToMany(mappedBy: 'teacher', targetEntity: Rating::class)]
    private Collection $ratings;

    #[ORM\Column(nullable: true)]
    #[Groups(['teacher:list', 'teacher:pricing', 'teacher:disponibilities', 'user:teachers', 'teacher:update'])]
    private ?float $price = null;

    #[ORM\ManyToOne(inversedBy: 'teachers')]
    #[Groups(['teacher:list', 'teacher:pricing', 'teacher:disponibilities', 'user:teachers'])]
    private ?Currency $currency = null;

    #[ORM\OneToMany(mappedBy: 'teacher', targetEntity: UserTeacher::class)]
    private Collection $students;

    #[ORM\Column(length: 255)]
    #[Groups(['teacher:list', 'teacher:become', 'teacher:update'])]
    private ?string $shortDescription = null;

    #[ORM\OneToMany(mappedBy: 'teacher', targetEntity: SpokenLanguage::class, cascade: ["persist"])]
    #[Groups(['teacher:list', 'teacher:become', 'teacher:update'])]
    private Collection $spokenLanguages;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['teacher:show', 'teacher:become', 'teacher:update'])]
    private ?string $experience = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['teacher:show', 'teacher:become', 'teacher:update'])]
    private ?string $motivation = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['teacher:list', 'teacher:media'])]
    private ?string $link = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['teacher:show', 'teacher:media'])]
    private ?string $video = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['teacher:show', 'teacher:disponibilities', 'teacher:update'])]
    private ?string $timezone = null;

    #[ORM\OneToMany(mappedBy: 'teacher', targetEntity: Disponibility::class, cascade: ["persist"])]
    #[Groups(['teacher:show', 'teacher:disponibilities'])]
    private Collection $disponibilities;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $submitedAt = null;

    #[ORM\ManyToOne]
    #[Groups(['teacher:list'])]
    private ?Language $language;

    #[ORM\Column]
    #[Groups(['teacher:show'])]
    private ?int $step = 1;

    #[ORM\OneToMany(mappedBy: 'teacher', targetEntity: TeacherCertification::class, cascade: ["persist"])]
    #[Groups(['teacher:show', 'teacher:certifications'])]
    private Collection $teacherCertifications;

    #[ORM\OneToMany(mappedBy: 'teacher', targetEntity: TeacherFormation::class, cascade: ["persist"])]
    #[Groups(['teacher:show', 'teacher:certifications'])]
    private Collection $teacherFormations;

    #[ORM\OneToMany(mappedBy: 'teacher', targetEntity: TeachingLanguage::class, cascade: ["persist"], orphanRemoval: true)]
    #[Groups(['teacher:show', 'teacher:become'])]
    private Collection $teachingLanguages;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['teacher:list', 'user:inbox', 'teacher:update'])]
    private ?string $profile = null;

    #[ORM\Column(length: 255, options: ['default' => self::STATUS_WAITING])]
    private ?string $status = null;

    public function __construct()
    {
        $this->dateConstructor();
        $this->ratingableConstructor();
        $this->activableConstructor();

        $this->languages = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->plannings = new ArrayCollection();
        $this->courses = new ArrayCollection();
        $this->socials = new ArrayCollection();
        $this->students = new ArrayCollection();
        $this->spokenLanguages = new ArrayCollection();
        $this->disponibilities = new ArrayCollection();
        $this->teacherCertifications = new ArrayCollection();
        $this->teacherFormations = new ArrayCollection();
        $this->teachingLanguages = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Language>
     */
    public function getLanguages(): Collection
    {
        return $this->languages;
    }

    public function addLanguage(Language $language): static
    {
        if (!$this->languages->contains($language)) {
            $this->languages->add($language);
        }

        return $this;
    }

    public function removeLanguage(Language $language): static
    {
        $this->languages->removeElement($language);

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    /**
     * @return Collection<int, Planning>
     */
    public function getPlannings(): Collection
    {
        return $this->plannings;
    }

    public function addPlanning(Planning $planning): static
    {
        if (!$this->plannings->contains($planning)) {
            $this->plannings->add($planning);
            $planning->setTeacher($this);
        }

        return $this;
    }

    public function removePlanning(Planning $planning): static
    {
        if ($this->plannings->removeElement($planning)) {
            // set the owning side to null (unless already changed)
            if ($planning->getTeacher() === $this) {
                $planning->setTeacher(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Course>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    #[Groups(['teacher:list'])]
    public function getCoursesCount(): int
    {
        return $this->courses->count();
    }

    public function addCourse(Course $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
            $course->setTeacher($this);
        }

        return $this;
    }

    public function removeCourse(Course $course): static
    {
        if ($this->courses->removeElement($course)) {
            // set the owning side to null (unless already changed)
            if ($course->getTeacher() === $this) {
                $course->setTeacher(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, Social>
     */
    public function getSocials(): Collection
    {
        return $this->socials;
    }

    public function addSocial(Social $social): static
    {
        if (!$this->socials->contains($social)) {
            $this->socials->add($social);
            $social->setTeacher($this);
        }

        return $this;
    }

    public function removeSocial(Social $social): static
    {
        if ($this->socials->removeElement($social)) {
            // set the owning side to null (unless already changed)
            if ($social->getTeacher() === $this) {
                $social->setTeacher(null);
            }
        }

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return Collection<int, UserTeacher>
     */
    public function getStudents(): Collection
    {
        return $this->students;
    }

    //#[Groups(['teacher:list'])]
    /*public function getStudentsCount(): int
    {
        $stutents = [];
        $courses = $this->courses;

        $courses->map(function (Course $course) use (&$stutents) {
            //$stutents[] = $course->get
        });

        return count($stutents);
    }*/

    #[Groups(['teacher:list'])]
    public function getStudentsCount()
    {
        return $this->students->count();
    }

    public function addStudent(UserTeacher $student): static
    {
        if (!$this->students->contains($student)) {
            $this->students->add($student);
            $student->setTeacher($this);
        }

        return $this;
    }

    public function removeStudent(UserTeacher $student): static
    {
        if ($this->students->removeElement($student)) {
            // set the owning side to null (unless already changed)
            if ($student->getTeacher() === $this) {
                $student->setTeacher(null);
            }
        }

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    /**
     * @return Collection<int, SpokenLanguage>
     */
    public function getSpokenLanguages(): Collection
    {
        return $this->spokenLanguages;
    }

    public function setSpokenLanguages(Collection $spokenLanguages): static
    {
        $this->spokenLanguages = $spokenLanguages;
        return $this;
    }

    public function addSpokenLanguage(SpokenLanguage $spokenLanguage): static
    {
        if (!$this->spokenLanguages->contains($spokenLanguage)) {
            $this->spokenLanguages->add($spokenLanguage);
            $spokenLanguage->setTeacher($this);
        }

        return $this;
    }

    public function removeSpokenLanguage(SpokenLanguage $spokenLanguage): static
    {
        if ($this->spokenLanguages->removeElement($spokenLanguage)) {
            // set the owning side to null (unless already changed)
            if ($spokenLanguage->getTeacher() === $this) {
                $spokenLanguage->setTeacher(null);
            }
        }

        return $this;
    }

    public function getExperience(): ?string
    {
        return $this->experience;
    }

    public function setExperience(?string $experience): static
    {
        $this->experience = $experience;

        return $this;
    }

    public function getMotivation(): ?string
    {
        return $this->motivation;
    }

    public function setMotivation(?string $motivation): static
    {
        $this->motivation = $motivation;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getVideo(): ?string
    {
        return $this->video;
    }

    public function setVideo(?string $video): static
    {
        $this->video = $video;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * @return Collection<int, Disponibility>
     */
    public function getDisponibilities(): Collection
    {
        return $this->disponibilities;
    }

    public function addDisponibility(Disponibility $disponibility): static
    {
        if (!$this->disponibilities->contains($disponibility)) {
            $this->disponibilities->add($disponibility);
            $disponibility->setTeacher($this);
        }

        return $this;
    }

    public function removeDisponibility(Disponibility $disponibility): static
    {
        if ($this->disponibilities->removeElement($disponibility)) {
            // set the owning side to null (unless already changed)
            if ($disponibility->getTeacher() === $this) {
                $disponibility->setTeacher(null);
            }
        }

        return $this;
    }

    /**
     * @throws \Exception
     */
    #[Groups(['teacher:show', 'teacher:disponibilities:list'])]
    public function getDisponibilitiesList(): array
    {
        $now = new DateTimeImmutable();
        if ($this->getTimezone())
            $now->setTimezone(new \DateTimeZone($this->getTimezone()));
        $end = $now->modify('+ 15days');
        $interval = DateInterval::createFromDateString('1 day');
        $daterange = new DatePeriod($now, $interval, $end);

        $plannings = [];

        foreach ($daterange as $day) {
            $this->disponibilities->map(function (Disponibility $disponibility) use ($now, $day, &$plannings) {
                $d = $day->format('l');
                if ($disponibility->isIsActive() && ucfirst($disponibility->getDay()) === $d) {
                    /*$s = $now->modify("{$disponibility->getDay()} this week");
                    if ($s->diff($now)->invert === 0) {
                        $s = $now->modify("{$disponibility->getDay()} next week");
                    }*/
                    $start = $day->modify($disponibility->getStart());
                    $end = $day->modify($disponibility->getEnd());
                    $plannings = [...$plannings, ...Idioma::dateRange($start->format('Y-m-d H:i'), $end->format('Y-m-d H:i'))];
                }
                //dd($day, $disponibility, $plannings);
            });
        }

        $allreadyPlannings = [];
        $this->plannings->map(function (Planning $planning) use (&$allreadyPlannings) {
            $start = $planning->getStart()->format('Y-m-d H:i');
            $end = $planning->getEnd()->format('Y-m-d H:i');
            $allreadyPlannings = [...$allreadyPlannings, ...Idioma::dateRange($start, $end, '+30 minutes')];
        });

        return array_values(array_diff($plannings, $allreadyPlannings));
    }


    public function getSubmitedAt(): ?DateTimeImmutable
    {
        return $this->submitedAt;
    }

    public function setSubmitedAt(?DateTimeImmutable $submitedAt): static
    {
        $this->submitedAt = $submitedAt;

        return $this;
    }

    public function isIsVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getLanguage(): ?Language
    {
        return $this->language;
    }

    public function setLanguage(?Language $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getStep(): ?int
    {
        return $this->step;
    }

    public function setStep(int $step): static
    {
        $this->step = $step;

        return $this;
    }

    /**
     * @return Collection<int, TeacherCertification>
     */
    public function getTeacherCertifications(): Collection
    {
        return $this->teacherCertifications;
    }

    public function addTeacherCertification(TeacherCertification $teacherCertification): static
    {
        if (!$this->teacherCertifications->contains($teacherCertification)) {
            $this->teacherCertifications->add($teacherCertification);
            $teacherCertification->setTeacher($this);
        }

        return $this;
    }

    public function removeTeacherCertification(TeacherCertification $teacherCertification): static
    {
        if ($this->teacherCertifications->removeElement($teacherCertification)) {
            // set the owning side to null (unless already changed)
            if ($teacherCertification->getTeacher() === $this) {
                $teacherCertification->setTeacher(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TeacherFormation>
     */
    public function getTeacherFormations(): Collection
    {
        return $this->teacherFormations;
    }

    public function addTeacherFormation(TeacherFormation $teacherFormation): static
    {
        if (!$this->teacherFormations->contains($teacherFormation)) {
            $this->teacherFormations->add($teacherFormation);
            $teacherFormation->setTeacher($this);
        }

        return $this;
    }

    public function removeTeacherFormation(TeacherFormation $teacherFormation): static
    {
        if ($this->teacherFormations->removeElement($teacherFormation)) {
            // set the owning side to null (unless already changed)
            if ($teacherFormation->getTeacher() === $this) {
                $teacherFormation->setTeacher(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TeachingLanguage>
     */
    public function getTeachingLanguages(): Collection
    {
        return $this->teachingLanguages;
    }

    public function setTeachingLanguages(ArrayCollection $teachingLanguages): static
    {
        $this->teachingLanguages = $teachingLanguages;
        return $this;
    }

    public function addTeachingLanguage(TeachingLanguage $teachingLanguage): static
    {
        if (!$this->teachingLanguages->contains($teachingLanguage)) {
            $this->teachingLanguages->add($teachingLanguage);
            $teachingLanguage->setTeacher($this);
        }

        return $this;
    }

    public function removeTeachingLanguage(TeachingLanguage $teachingLanguage): static
    {
        if ($this->teachingLanguages->removeElement($teachingLanguage)) {
            // set the owning side to null (unless already changed)
            if ($teachingLanguage->getTeacher() === $this) {
                $teachingLanguage->setTeacher(null);
            }
        }

        return $this;
    }

    public function getProfile(): ?string
    {
        return $this->profile;
    }

    public function setProfile(?string $profile): static
    {
        $this->profile = $profile;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
    public static function getStatusList(): array
    {
        return [
            'En attente de validation' => self::STATUS_WAITING,
            'Validé' => self::STATUS_VALIDATED,
            'Approuvé' => self::STATUS_APPROVAL,
            'Désactivé' => self::STATUS_DEACTIVATE,
            'Bloqué' => self::STATUS_BLOCKED,
        ];
    }

    public static function getStatusBadge(): array
    {
        return [
            'warning',
            'success',
            'success',
            'danger',
            'dark',
        ];
    }

    public static function getStatusListForView(): array
    {
        return array_flip(self::getStatusList());
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }
}
