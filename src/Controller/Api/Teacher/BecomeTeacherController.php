<?php

namespace App\Controller\Api\Teacher;

use App\Entity\User;
use App\Model\CreateTeacherModel;
use App\Service\Teacher\TeacherBecomeRequestParser;
use App\Service\Teacher\TeacherManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class BecomeTeacherController extends AbstractController
{
    public function __invoke(
        Request $request,
        TeacherBecomeRequestParser $parser,
        ValidatorInterface $validator,
        TeacherManager $manager,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        if ($existing = $user->getTeacher()) {
            $input = $parser->parse($request);
            $violations = $validator->validate($input);
            if ($violations->count() > 0) {
                throw new ValidationFailedException($input, $violations);
            }

            $manager->updateFromBecomeInput($existing, $input);

            return $this->json([
                'id' => $existing->getId(),
                'status' => $existing->getStatus(),
                'token' => $jwtManager->create($user),
                'alreadyExists' => true,
            ]);
        }

        $input = $parser->parse($request);
        $violations = $validator->validate($input);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($input, $violations);
        }

        $teacher = $manager->create(new CreateTeacherModel(
            $input->fullname,
            $input->firstname,
            $input->lastname,
            $input->country,
            $input->language,
            $input->phone,
            $input->price,
            $input->currency,
            $input->profile,
            $input->video,
            $input->videoPoster,
            $input->link,
            $input->shortDescription,
            $input->description,
            $input->experience,
            $input->motivation,
            $input->hookTitle,
            $input->timezone,
            $input->certifications,
            $input->formations,
            $input->availabilities,
            $input->spokenLanguages,
            $input->languages,
        ));

        return $this->json([
            'id' => $teacher->getId(),
            'status' => $teacher->getStatus(),
            'token' => $jwtManager->create($user),
        ], 201);
    }
}
