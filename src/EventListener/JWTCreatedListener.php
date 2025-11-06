<?php

namespace App\EventListener;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
class JWTCreatedListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();

        // Récupérer la première subscription active
        $subscription = null;
        foreach ($user->getSubscriptions() as $sub) {
            if ($sub->getStatus() === 'active') {
                $subscription = $sub;
                break;
            }
        }

        // Si pas de subscription active, prendre la première
        if (!$subscription && count($user->getSubscriptions()) > 0) {
            $subscription = $user->getSubscriptions()[0];
        }

        // Ajouter les données utilisateur au payload JWT
        $payload['user'] = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'phone' => $user->getPhone(),
            'avatar' => $user->getAvatar(),
            'isVerified' => true,
            'isActive' => true,
        ];

        // Ajouter la subscription si elle existe
        if ($subscription && $subscription->getPlan()) {
            $plan = $subscription->getPlan();
            $payload['subscription'] = [
                'id' => $subscription->getId(),
                'type' => $plan->getType(),
                'status' => $subscription->getStatus(),
                'startDate' => $subscription->getStartDate()?->format('c'),
                'endDate' => $subscription->getEndDate()?->format('c'),
                'price' => $plan->getPrice(),
                'autoRenew' => $subscription->isAutoRenew(),
            ];
        }

        $event->setData($payload);
    }
}
