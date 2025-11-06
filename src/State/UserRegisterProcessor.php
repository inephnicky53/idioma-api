<?php

namespace App\State;

use App\Entity\User;
use App\Entity\Subscription;
use App\Entity\SubscriptionPlan;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use DateTime;

class UserRegisterProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof User) {
            return $data;
        }

        // Vérifier si c'est une inscription (POST sur /auth/register)
        if ($operation->getName() !== 'register') {
            return $data;
        }

        // Vérifier si l'email existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data->getEmail()]);
        if ($existingUser) {
            throw new \Exception('Cet email est déjà utilisé');
        }

        // Hacher le mot de passe
        $plainPassword = $data->getPassword();
        if (!$plainPassword) {
            throw new \Exception('Password is required');
        }

        $hashedPassword = $this->passwordHasher->hashPassword($data, $plainPassword);
        $data->setPassword($hashedPassword);

        // Définir les valeurs par défaut
        $data->setIsActive(true);
        $data->setCreatedAt(new DateTime());

        // Sauvegarder l'utilisateur
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        // Si c'est une inscription au club (avec les champs club remplis)
        if ($data->isClubMember() || $data->getLevel()) {
            // Récupérer ou créer le plan "Club Plan"
            $clubPlan = $this->entityManager->getRepository(SubscriptionPlan::class)->findOneBy(['type' => 'club']);
            if (!$clubPlan) {
                $clubPlan = new SubscriptionPlan();
                $clubPlan->setName('Club Plan');
                $clubPlan->setDescription('Plan Club');
                $clubPlan->setPrice(50.00);
                $clubPlan->setDurationDays(30);
                $clubPlan->setType('club');
                $clubPlan->setSessionsLimit(4);
                $clubPlan->setIsActive(true);
                $this->entityManager->persist($clubPlan);
                $this->entityManager->flush();
            }

            // Créer la subscription
            $subscription = new Subscription();
            $subscription->setUser($data);
            $subscription->setPlan($clubPlan);
            $subscription->setStartDate(new DateTime());
            $subscription->setEndDate((new DateTime())->modify('+30 days'));
            $subscription->setStatus('active');
            $subscription->setSessionsUsed(0);
            $subscription->setAutoRenew(true);

            $this->entityManager->persist($subscription);
            $this->entityManager->flush();

            // Marquer l'utilisateur comme membre du club
            $data->setIsClubMember(true);
            $this->entityManager->persist($data);
            $this->entityManager->flush();
        }

        return $data;
    }
}
