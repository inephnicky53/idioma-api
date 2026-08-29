<?php

declare(strict_types=1);

namespace App\Controller\Api\Teacher;

use App\Entity\Social;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/teacher/socials')]
#[IsGranted('ROLE_USER')]
class TeacherSocialsController extends AbstractController
{
    private const SOCIAL_TYPES = [
        'twitter' => 'icon-twitter',
        'facebook' => 'icon-facebook',
        'instagram' => 'icon-instagram',
        'linkedin' => 'icon-linkedin',
        'youtube' => 'icon-play',
        'website' => 'icon-worldwide',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'api_teacher_socials_get', methods: ['GET'])]
    public function getSocials(): JsonResponse
    {
        $teacher = $this->requireTeacher();

        return $this->json([
            'socials' => $this->serializeSocials($teacher->getSocials()),
            'fields' => $this->emptyFieldsFromSocials($teacher->getSocials()),
        ]);
    }

    #[Route('', name: 'api_teacher_socials_update', methods: ['PUT', 'PATCH'])]
    public function updateSocials(Request $request): JsonResponse
    {
        $teacher = $this->requireTeacher();
        $payload = json_decode($request->getContent(), true) ?: [];

        foreach (self::SOCIAL_TYPES as $type => $icon) {
            if (!array_key_exists($type, $payload)) {
                continue;
            }

            $link = trim((string) ($payload[$type] ?? ''));
            $existing = $this->findSocialByType($teacher->getSocials(), $type);

            if ($link === '') {
                if ($existing) {
                    $teacher->removeSocial($existing);
                    $this->em->remove($existing);
                }
                continue;
            }

            if (!filter_var($link, FILTER_VALIDATE_URL)) {
                return $this->json(
                    ['message' => sprintf('URL invalide pour %s.', $type)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if ($existing) {
                $existing->setLink($link);
                $existing->setIcon($icon);
                continue;
            }

            $social = (new Social())
                ->setType($type)
                ->setLink($link)
                ->setIcon($icon);
            $teacher->addSocial($social);
        }

        $this->em->flush();

        return $this->json([
            'message' => 'Réseaux sociaux enregistrés.',
            'socials' => $this->serializeSocials($teacher->getSocials()),
            'fields' => $this->emptyFieldsFromSocials($teacher->getSocials()),
        ]);
    }

    private function requireTeacher()
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacher = $user->getTeacher();
        if (!$teacher) {
            throw $this->createAccessDeniedException('Profil professeur requis.');
        }

        return $teacher;
    }

    private function findSocialByType(iterable $socials, string $type): ?Social
    {
        foreach ($socials as $social) {
            if ($social->getType() === $type) {
                return $social;
            }
        }

        return null;
    }

    private function serializeSocials(iterable $socials): array
    {
        $items = [];
        foreach ($socials as $social) {
            $items[] = [
                'id' => $social->getId(),
                'type' => $social->getType(),
                'link' => $social->getLink(),
                'icon' => $social->getIcon(),
            ];
        }

        return $items;
    }

    private function emptyFieldsFromSocials(iterable $socials): array
    {
        $fields = array_fill_keys(array_keys(self::SOCIAL_TYPES), '');
        foreach ($socials as $social) {
            $type = $social->getType();
            if (array_key_exists($type, $fields)) {
                $fields[$type] = $social->getLink() ?? '';
            }
        }

        return $fields;
    }
}
