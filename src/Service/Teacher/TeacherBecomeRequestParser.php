<?php

namespace App\Service\Teacher;

use App\Dto\CreateTeacherInput;
use App\Model\AvailabilityModel;
use App\Model\CertificationModel;
use App\Model\FormationModel;
use App\Model\LanguageModel;
use App\Model\TimeSlot;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class TeacherBecomeRequestParser
{
    public function __construct(private readonly TeacherBecomeUploadService $uploads)
    {
    }

    public function parse(Request $request): CreateTeacherInput
    {
        $data = $request->request->all();
        if ($data === [] && $request->files->count() === 0) {
            throw new BadRequestHttpException('Aucune donnée reçue. Envoyez un formulaire multipart.');
        }

        $input = new CreateTeacherInput();
        $input->fullname = $this->stringOrNull($data['fullname'] ?? null);
        $input->firstname = $this->stringOrNull($data['firstname'] ?? null);
        $input->lastname = $this->stringOrNull($data['lastname'] ?? null);
        $input->country = $this->stringOrNull($data['country'] ?? null);
        $input->language = $this->stringOrNull($data['language'] ?? null);
        $input->phone = $this->stringOrNull($data['phone'] ?? null);
        $input->price = isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null;
        $input->currency = $this->stringOrNull($data['currency'] ?? null);
        $input->link = $this->stringOrNull($data['link'] ?? null);
        $input->shortDescription = $this->stringOrNull($data['shortDescription'] ?? null);
        $input->description = $this->stringOrNull($data['description'] ?? null);
        $input->experience = $this->stringOrNull($data['experience'] ?? null);
        $input->motivation = $this->stringOrNull($data['motivation'] ?? null);
        $input->hookTitle = $this->stringOrNull($data['hookTitle'] ?? null);
        $input->timezone = $this->stringOrNull($data['timezone'] ?? null);

        $photo = $request->files->get('photo');
        if ($photo instanceof UploadedFile) {
            $input->profile = $this->uploads->store($photo, 'photos');
        }

        $video = $request->files->get('video');
        if ($video instanceof UploadedFile) {
            $input->video = $this->uploads->store($video, 'videos');
        }

        $input->certifications = $this->mapCertifications($data['certifications'] ?? [], $request);
        $input->formations = $this->mapFormations($data['formations'] ?? [], $request);
        $input->availabilities = $this->mapAvailabilities($data['availabilities'] ?? []);
        $input->spokenLanguages = $this->mapLanguages($data['spokenLanguages'] ?? []);
        $input->languages = $this->mapLanguages($data['languages'] ?? []);

        return $input;
    }

    /**
     * @param array<mixed> $items
     *
     * @return CertificationModel[]
     */
    private function mapCertifications(array $items, Request $request): array
    {
        $models = [];
        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $model = new CertificationModel();
            $model->certification = $this->stringOrNull($item['certification'] ?? null);
            $model->language = $this->stringOrNull($item['language'] ?? null);
            $model->yearStart = $this->parseYear($item['yearStart'] ?? null);
            $model->yearEnd = $this->parseYear($item['yearEnd'] ?? null);

            $proof = $request->files->get('certProof_'.$index);
            if ($proof instanceof UploadedFile) {
                $model->proofImage = $this->uploads->store($proof, 'certifications');
            }

            $models[] = $model;
        }

        return $models;
    }

    /**
     * @param array<mixed> $items
     *
     * @return FormationModel[]
     */
    private function mapFormations(array $items, Request $request): array
    {
        $models = [];
        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $proof = $request->files->get('formationProof_'.$index);
            $proofImage = $proof instanceof UploadedFile
                ? $this->uploads->store($proof, 'formations')
                : null;

            $models[] = new FormationModel(
                university: $this->stringOrNull($item['university'] ?? null),
                speciality: $this->stringOrNull($item['speciality'] ?? null),
                certificate: $this->stringOrNull($item['certificate'] ?? null),
                yearStart: $this->stringOrNull($item['yearStart'] ?? null),
                yearEnd: $this->stringOrNull($item['yearEnd'] ?? null),
                proofImage: $proofImage,
            );
        }

        return $models;
    }

    /**
     * @param array<mixed> $items
     *
     * @return AvailabilityModel[]
     */
    private function mapAvailabilities(array $items): array
    {
        $models = [];
        foreach ($items as $day => $slots) {
            if (!is_string($day) || !is_array($slots)) {
                continue;
            }

            $programs = [];
            foreach (array_values($slots) as $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                $start = $this->stringOrNull($slot['start'] ?? null);
                $end = $this->stringOrNull($slot['end'] ?? null);
                if (!$start || !$end) {
                    continue;
                }
                $timeSlot = new TimeSlot();
                $timeSlot->start = $start;
                $timeSlot->end = $end;
                $programs[] = $timeSlot;
            }

            if ($programs !== []) {
                $models[] = new AvailabilityModel($day, $programs);
            }
        }

        return $models;
    }

    /**
     * @param array<mixed> $items
     *
     * @return LanguageModel[]
     */
    private function mapLanguages(array $items): array
    {
        $models = [];
        foreach (array_values($items) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $language = $this->stringOrNull($item['language'] ?? null);
            if (!$language) {
                continue;
            }
            $models[] = new LanguageModel($language, $this->stringOrNull($item['level'] ?? null));
        }

        return $models;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseYear(mixed $value): ?\DateTimeImmutable
    {
        $value = $this->stringOrNull($value);
        if (!$value) {
            return null;
        }

        if (preg_match('/^\d{4}$/', $value)) {
            return new \DateTimeImmutable($value.'-01-01');
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
