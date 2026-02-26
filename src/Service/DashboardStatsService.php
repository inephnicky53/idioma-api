<?php

namespace App\Service;

use App\Repository\UserRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\PaymentRepository;
use App\Repository\CheckInRepository;

class DashboardStatsService
{
    public function __construct(
        private UserRepository $userRepository,
        private SubscriptionRepository $subscriptionRepository,
        private PaymentRepository $paymentRepository,
        private CheckInRepository $checkInRepository,
    ) {}

    public function getStats(string $period = 'today'): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $yesterday = (clone $today)->modify('-1 day');
        $startOfWeek = (clone $today)->modify('monday this week')->setTime(0, 0, 0);
        $startOfLastWeek = (clone $startOfWeek)->modify('-7 days');
        $startOfMonth = (clone $today)->modify('first day of this month')->setTime(0, 0, 0);
        $startOfLastMonth = (clone $startOfMonth)->modify('-1 month');

        return [
            'users' => $this->getUserStats($today, $yesterday, $startOfMonth, $startOfLastMonth, $period),
            'subscriptions' => $this->getSubscriptionStats($today, $yesterday, $startOfMonth, $startOfLastMonth, $period),
            'payments' => $this->getPaymentStats($today, $yesterday, $startOfWeek, $startOfLastWeek, $startOfMonth, $startOfLastMonth, $period),
            'checkIns' => $this->getCheckInStats($today, $yesterday, $startOfWeek, $startOfLastWeek, $startOfMonth, $startOfLastMonth, $period),
        ];
    }

    private function getUserStats(\DateTime $today, \DateTime $yesterday, \DateTime $startOfMonth, \DateTime $startOfLastMonth, string $period = 'today'): array
    {
        $totalUsers = $this->userRepository->getTotalUsersCount();
        $activeUsers = $this->userRepository->getActiveUsersCount();
        $usersWithActiveSubscriptions = $this->userRepository->getUsersWithActiveSubscriptionsCount();

        $newUsersToday = $this->userRepository->getNewUsersCount($today, $today);
        $newUsersYesterday = $this->userRepository->getNewUsersCount($yesterday, $yesterday);
        $newUsersThisMonth = $this->userRepository->getNewUsersCount($startOfMonth, $today);
        $newUsersLastMonth = $this->userRepository->getNewUsersCount($startOfLastMonth, (clone $startOfLastMonth)->modify('last day of this month'));

        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'withActiveSubscriptions' => $usersWithActiveSubscriptions,
            'newToday' => $newUsersToday,
            'newYesterday' => $newUsersYesterday,
            'newTodayVsYesterday' => $newUsersYesterday > 0 ? round((($newUsersToday - $newUsersYesterday) / $newUsersYesterday) * 100, 2) : 0,
            'newThisMonth' => $newUsersThisMonth,
            'newLastMonth' => $newUsersLastMonth,
            'newThisMonthVsLastMonth' => $newUsersLastMonth > 0 ? round((($newUsersThisMonth - $newUsersLastMonth) / $newUsersLastMonth) * 100, 2) : 0,
        ];
    }

    private function getSubscriptionStats(\DateTime $today, \DateTime $yesterday, \DateTime $startOfMonth, \DateTime $startOfLastMonth, string $period = 'today'): array
    {
        $activeSubscriptions = $this->subscriptionRepository->getActiveSubscriptionsCount();
        $expiredSubscriptions = $this->subscriptionRepository->getExpiredSubscriptionsCount();

        $newSubscriptionsToday = $this->subscriptionRepository->getNewSubscriptionsCount($today, $today);
        $newSubscriptionsYesterday = $this->subscriptionRepository->getNewSubscriptionsCount($yesterday, $yesterday);
        $newSubscriptionsThisMonth = $this->subscriptionRepository->getNewSubscriptionsCount($startOfMonth, $today);
        $newSubscriptionsLastMonth = $this->subscriptionRepository->getNewSubscriptionsCount($startOfLastMonth, (clone $startOfLastMonth)->modify('last day of this month'));

        return [
            'active' => $activeSubscriptions,
            'expired' => $expiredSubscriptions,
            'newToday' => $newSubscriptionsToday,
            'newYesterday' => $newSubscriptionsYesterday,
            'newTodayVsYesterday' => $newSubscriptionsYesterday > 0 ? round((($newSubscriptionsToday - $newSubscriptionsYesterday) / $newSubscriptionsYesterday) * 100, 2) : 0,
            'newThisMonth' => $newSubscriptionsThisMonth,
            'newLastMonth' => $newSubscriptionsLastMonth,
            'newThisMonthVsLastMonth' => $newSubscriptionsLastMonth > 0 ? round((($newSubscriptionsThisMonth - $newSubscriptionsLastMonth) / $newSubscriptionsLastMonth) * 100, 2) : 0,
        ];
    }

    private function getPaymentStats(\DateTime $today, \DateTime $yesterday, \DateTime $startOfWeek, \DateTime $startOfLastWeek, \DateTime $startOfMonth, \DateTime $startOfLastMonth, string $period = 'today'): array
    {
        // Paiements complétés (anciennement "revenu")
        $paymentsToday = $this->paymentRepository->getCompletedPaymentsCount($today, $today);
        $paymentsYesterday = $this->paymentRepository->getCompletedPaymentsCount($yesterday, $yesterday);
        $paymentsThisMonth = $this->paymentRepository->getCompletedPaymentsCount($startOfMonth, $today);
        $paymentsLastMonth = $this->paymentRepository->getCompletedPaymentsCount($startOfLastMonth, (clone $startOfLastMonth)->modify('last day of this month'));

        // Paiements en attente (WAIT)
        $waitPaymentsToday = $this->paymentRepository->getWaitPaymentsCount($today, $today);
        $waitPaymentsThisMonth = $this->paymentRepository->getWaitPaymentsCount($startOfMonth, $today);

        // Paiements échoués (FAILED)
        $failedPaymentsToday = $this->paymentRepository->getFailedPaymentsCount($today, $today);
        $failedPaymentsThisMonth = $this->paymentRepository->getFailedPaymentsCount($startOfMonth, $today);

        // Paiements CASH (tous les statuts)
        $cashPaymentsToday = $this->paymentRepository->getCashPaymentsCount($today, $today);
        $cashPaymentsThisMonth = $this->paymentRepository->getCashPaymentsCount($startOfMonth, $today);

        // Paiements CASH en attente (WAIT)
        $cashWaitPaymentsToday = $this->paymentRepository->getCashWaitPaymentsCount($today, $today);
        $cashWaitPaymentsThisMonth = $this->paymentRepository->getCashWaitPaymentsCount($startOfMonth, $today);

        // Paiements CASH complétés
        $cashCompletedPaymentsToday = $this->paymentRepository->getCashCompletedPaymentsCount($today, $today);
        $cashCompletedPaymentsThisMonth = $this->paymentRepository->getCashCompletedPaymentsCount($startOfMonth, $today);

        return [
            'paymentsToday' => $paymentsToday,
            'paymentsYesterday' => $paymentsYesterday,
            'paymentsTodayVsYesterday' => $paymentsYesterday > 0 ? round((($paymentsToday - $paymentsYesterday) / $paymentsYesterday) * 100, 2) : 0,
            'paymentsThisMonth' => $paymentsThisMonth,
            'paymentsLastMonth' => $paymentsLastMonth,
            'paymentsThisMonthVsLastMonth' => $paymentsLastMonth > 0 ? round((($paymentsThisMonth - $paymentsLastMonth) / $paymentsLastMonth) * 100, 2) : 0,
            'waitPaymentsToday' => $waitPaymentsToday,
            'waitPaymentsThisMonth' => $waitPaymentsThisMonth,
            'failedPaymentsToday' => $failedPaymentsToday,
            'failedPaymentsThisMonth' => $failedPaymentsThisMonth,
            'cashPaymentsToday' => $cashPaymentsToday,
            'cashPaymentsThisMonth' => $cashPaymentsThisMonth,
            'cashWaitPaymentsToday' => $cashWaitPaymentsToday,
            'cashWaitPaymentsThisMonth' => $cashWaitPaymentsThisMonth,
            'cashCompletedPaymentsToday' => $cashCompletedPaymentsToday,
            'cashCompletedPaymentsThisMonth' => $cashCompletedPaymentsThisMonth,
        ];
    }

    private function getCheckInStats(\DateTime $today, \DateTime $yesterday, \DateTime $startOfWeek, \DateTime $startOfLastWeek, \DateTime $startOfMonth, \DateTime $startOfLastMonth, string $period = 'today'): array
    {
        $checkInsToday = $this->checkInRepository->getTodayCheckInsCount();
        $checkInsThisWeek = $this->checkInRepository->getThisWeekCheckInsCount();
        $checkInsThisMonth = $this->checkInRepository->getThisMonthCheckInsCount();
        $activeCheckIns = $this->checkInRepository->getActiveCheckInsCount();

        return [
            'today' => $checkInsToday,
            'thisWeek' => $checkInsThisWeek,
            'thisMonth' => $checkInsThisMonth,
            'active' => $activeCheckIns,
        ];
    }
}

