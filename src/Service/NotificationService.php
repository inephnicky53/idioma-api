<?php


namespace App\Service;


use App\Entity\Notification;
use App\Entity\User;
use App\Event\NotificationCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

readonly class NotificationService
{
    public function __construct(
        private EntityManagerInterface   $em,
        private EventDispatcherInterface $dispatcher,
    )
    {
    }

    /**
     * Envoie une notification à un utilisateur.
     */
    public function notifyUser(User $user, string $title, string $message): Notification
    {
        $notification = (new Notification())
            ->setSubject($title)
            ->setContent($message)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUser($user);

        $this->em->persist($notification);
        $this->em->flush();

        $this->dispatcher->dispatch(new NotificationCreatedEvent($notification));

        return $notification;
    }

    /**
     * @return Notification[]
     */
    public function forUser(User $user): array
    {
        $repository = $this->em->getRepository(Notification::class);

        return $repository->findRecentForUser($user);
    }

    /**
     * Renvoie les salons auxquels l'utilisateur peut s'abonner.
     *
     * @return string[]
     */
    public function getChannelsForUser(User $user): array
    {

        return ['user/' . $user->getId(), 'public'];
    }

    private function getHashForEntity(object $entity): string
    {
        $hash = $entity::class;
        if (method_exists($entity, 'getId')) {
            $hash .= '::' . (string)$entity->getId();
        }

        return $hash;
    }
}
