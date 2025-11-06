<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\SubscriptionPlan;
use App\Entity\Subscription;
use App\Entity\Payment;
use App\Entity\CheckIn;
use App\Entity\TimeSlot;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create TimeSlots
        $timeSlots = [];

        $timeSlotsData = [
            ['Monday', '18:00', '19:00', 'Conversation générale'],
            ['Wednesday', '19:00', '20:00', 'Business English'],
            ['Friday', '17:30', '18:30', 'Français conversationnel'],
            ['Saturday', '10:00', '11:00', 'Atelier thématique'],
            ['Saturday', '14:00', '15:00', 'Débats et discussions'],
        ];

        foreach ($timeSlotsData as $data) {
            $timeSlot = new TimeSlot();
            $timeSlot->setDay($data[0]);
            $timeSlot->setStartTime($data[1]);
            $timeSlot->setEndTime($data[2]);
            // Note: le champ 'type' n'existe pas encore dans l'entité TimeSlot
            // On pourrait l'ajouter plus tard si nécessaire
            $manager->persist($timeSlot);
            $timeSlots[] = $timeSlot;
        }

        $manager->flush();

        // Create subscription plans
        $plans = [];

        $clubPlan = new SubscriptionPlan();
        $clubPlan->setName('Club Standard');
        $clubPlan->setType('club');
        $clubPlan->setDescription('Accès au club avec 4 sessions par mois');
        $clubPlan->setPrice('29.99');
        $clubPlan->setSessionsLimit(4);
        $clubPlan->setDurationDays(30);
        $manager->persist($clubPlan);
        $plans['club'] = $clubPlan;

        $formationPlan = new SubscriptionPlan();
        $formationPlan->setName('Formation Premium');
        $formationPlan->setType('formation');
        $formationPlan->setDescription('Accès aux formations en ligne');
        $formationPlan->setPrice('49.99');
        $formationPlan->setSessionsLimit(10);
        $formationPlan->setDurationDays(30);
        $manager->persist($formationPlan);
        $plans['formation'] = $formationPlan;

        $bothPlan = new SubscriptionPlan();
        $bothPlan->setName('Club + Formation');
        $bothPlan->setType('both');
        $bothPlan->setDescription('Accès complet au club et aux formations');
        $bothPlan->setPrice('69.99');
        $bothPlan->setSessionsLimit(15);
        $bothPlan->setDurationDays(30);
        $manager->persist($bothPlan);
        $plans['both'] = $bothPlan;

        // Create test users
        $users = [];

        // Admin user
        $admin = new User();
        $admin->setEmail('admin@idioma-club.com');
        $admin->setFirstName('Admin');
        $admin->setLastName('User');
        $admin->setPhone('+33612345678');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Admin123!@'));
        $manager->persist($admin);
        $users['admin'] = $admin;

        // Regular users
        for ($i = 1; $i <= 5; $i++) {
            $user = new User();
            $user->setEmail("user{$i}@idioma-club.com");
            $user->setFirstName("User");
            $user->setLastName("Test{$i}");
            $user->setPhone("+3361234567{$i}");
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'User123!@'));
            $manager->persist($user);
            $users["user{$i}"] = $user;
        }

        $manager->flush();

        // Create subscriptions for users
        foreach ($users as $key => $user) {
            if ($key === 'admin') continue;

            // Active subscription
            $subscription = new Subscription();
            $subscription->setUser($user);
            $subscription->setPlan($plans['club']);
            $subscription->setStartDate(new \DateTime('-10 days'));
            $subscription->setEndDate(new \DateTime('+20 days'));
            $subscription->setStatus('active');
            $subscription->setSessionsUsed(2);
            $subscription->setAutoRenew(true);
            $manager->persist($subscription);

            // Create payments for this subscription
            $payment = new Payment();
            $payment->setUser($user);
            $payment->setSubscriptionPlan($plans['club']);
            $payment->setAmount('29.99');
            $payment->setStatus('completed');
            $payment->setPaymentMethod('card');
            $payment->setPaidAt(new \DateTime('-10 days'));
            $manager->persist($payment);

            // Create check-ins
            for ($j = 0; $j < 3; $j++) {
                $checkIn = new CheckIn();
                $checkIn->setUser($user);
                $checkIn->setLocation('Salle ' . ($j + 1));
                $checkIn->setCheckedInAt(new \DateTime("-{$j} days"));
                $manager->persist($checkIn);
            }
        }

        $manager->flush();
    }
}
