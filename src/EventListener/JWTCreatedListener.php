<?php


namespace App\EventListener;


use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class JWTCreatedListener
{
    public function __construct(
        private readonly RequestStack   $request,
        private readonly UploaderHelper $uploaderHelper
    )
    {
    }

    /**
     * @param JWTCreatedEvent $event
     * @return void
     */
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();
        $assetThumbnail = count($user->getThumbnails()) > 0 ?
            $this->uploaderHelper->asset($user->getThumbnail(), 'file') : null;
        $mainRequest = $this->request->getMainRequest();
        $schemeAndHttpHost = $mainRequest?->getSchemeAndHttpHost() ?? '';

        $payload = $event->getData();
        $payload['id'] = $user->getId();
        $payload['firstname'] = $user->getFirstName();
        $payload['name'] = $user->getName();
        $payload['postname'] = $user->getPostname();
        $payload['fullname'] = $user->getFullname();
        $payload['avatar'] = $user->getThumbnail() && $schemeAndHttpHost ? "{$schemeAndHttpHost}{$assetThumbnail}" : null;
        $payload['profile'] = $user->getProfile();
        $teacherProfile = $user->getTeacher()?->getProfile();
        if ($teacherProfile) {
            $payload['teacher_profile'] = $teacherProfile;
            if (!$payload['profile']) {
                $payload['profile'] = $teacherProfile;
            }
        }
        $payload['phone'] = $user->getPhone();
        $payload['email'] = $user->getEmail();
        $payload['isTeacher'] = (bool) $user->getTeacher();
        $payload['teacher_id'] = $user->getTeacher()?->getId();
        $payload['host'] = $schemeAndHttpHost;
        $payload['isActive'] = $user->isIsActive();
        $payload['roles'] = $user->getRoles();

        $event->setData($payload);
    }
}
