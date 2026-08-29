<?php

namespace App\Controller\Api\Inbox;

use App\Entity\Attachment;
use App\Entity\InboxMessage;
use App\Entity\InboxThread;
use App\Entity\User;
use App\Repository\InboxThreadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class InboxMessageFileController extends AbstractController
{
    private const MAX_BYTES = 20 * 1024 * 1024;

    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'application/zip',
        'audio/mpeg',
        'audio/wav',
        'video/mp4',
    ];

    public function __construct(
        private readonly InboxThreadRepository $threads,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/inbox_messages/upload', name: 'api_inbox_message_upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function upload(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $threadId = (int) $request->request->get('threadId', $request->request->get('thread', 0));
        $thread = $this->threads->find($threadId);
        if (!$thread instanceof InboxThread) {
            return $this->json(['error' => 'Conversation introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canAccessThread($user, $thread)) {
            return $this->json(['error' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->json(['error' => 'Fichier manquant ou invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return $this->json(['error' => 'Fichier trop volumineux (20 Mo max).'], Response::HTTP_BAD_REQUEST);
        }

        $mime = $file->getMimeType() ?: 'application/octet-stream';
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return $this->json(['error' => 'Type de fichier non autorisé.'], Response::HTTP_BAD_REQUEST);
        }

        $attachment = new Attachment();
        $attachment->setFile($file);
        $attachment->setUser($user);

        $body = trim((string) $request->request->get('body', ''));
        if ($body === '') {
            $body = $file->getClientOriginalName() ?: 'Fichier';
        }

        $message = new InboxMessage();
        $message->setAuthor($user);
        $message->setThread($thread);
        $message->setBody($body);
        $message->setAttachment($attachment);
        $thread->addMessage($message);

        $this->em->persist($attachment);
        $this->em->persist($message);
        $this->em->flush();

        return $this->json($this->serializeMessage($message), Response::HTTP_CREATED);
    }

    private function canAccessThread(User $user, InboxThread $thread): bool
    {
        if ($thread->getParticipants()->contains($user)) {
            return true;
        }

        $teacher = $thread->getTeacher();

        return $teacher && $teacher->getUser() === $user;
    }

    /** @return array<string, mixed> */
    private function serializeMessage(InboxMessage $message): array
    {
        $attachment = $message->getAttachment();

        return [
            'id' => $message->getId(),
            'body' => $message->getBody(),
            'receivedAt' => $message->getReceivedAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $message->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'author' => [
                'id' => $message->getAuthor()?->getId(),
                'email' => $message->getAuthor()?->getEmail(),
                'fullname' => $message->getAuthor()?->getFullname(),
            ],
            'attachment' => $attachment ? [
                'id' => $attachment->getId(),
                'name' => $attachment->getName(),
                'originalName' => $attachment->getOriginalName(),
                'mimeType' => $attachment->getMimeType(),
                'size' => $attachment->getSize(),
                'url' => $attachment->getUrl(),
            ] : null,
        ];
    }
}
