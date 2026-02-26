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
        // Déterminer les dates en fonction du filtre period
        $startDate = (clone $today)->setTime(0, 0, 0);
        $endDate = (clone $today)->setTime(23, 59, 59);
        $comparisonStartDate = (clone $yesterday)->setTime(0, 0, 0);
        $comparisonEndDate = (clone $yesterday)->setTime(23, 59, 59);

        if ($period === 'week') {
            $startDate = (clone $startOfWeek)->setTime(0, 0, 0);
            $endDate = (clone $today)->setTime(23, 59, 59);
            $comparisonStartDate = (clone $startOfLastWeek)->setTime(0, 0, 0);
            $comparisonEndDate = (clone $startOfLastWeek)->modify('+7 days')->setTime(23, 59, 59);
        } elseif ($period === 'month') {
            $startDate = (clone $startOfMonth)->setTime(0, 0, 0);
            $endDate = (clone $today)->setTime(23, 59, 59);
            $comparisonStartDate = (clone $startOfLastMonth)->setTime(0, 0, 0);
            $comparisonEndDate = (clone $startOfLastMonth)->modify('last day of this month')->setTime(23, 59, 59);
        }

        // Paiements complétés (anciennement "revenu")
        $paymentsCurrent = $this->paymentRepository->getCompletedPaymentsCount($startDate, $endDate);
        $paymentsComparison = $this->paymentRepository->getCompletedPaymentsCount($comparisonStartDate, $comparisonEndDate);

        // Paiements en attente (WAIT)
        $waitPaymentsCurrent = $this->paymentRepository->getWaitPaymentsCount($startDate, $endDate);

        // Paiements échoués (FAILED)
        $failedPaymentsCurrent = $this->paymentRepository->getFailedPaymentsCount($startDate, $endDate);

        // Paiements CASH (tous les statuts)
        $cashPaymentsCurrent = $this->paymentRepository->getCashPaymentsCount($startDate, $endDate);

        // Paiements CASH en attente (WAIT)
        $cashWaitPaymentsCurrent = $this->paymentRepository->getCashWaitPaymentsCount($startDate, $endDate);

        // Paiements CASH complétés
        $cashCompletedPaymentsCurrent = $this->paymentRepository->getCashCompletedPaymentsCount($startDate, $endDate);

        return [
            'paymentsCurrent' => $paymentsCurrent,
            'paymentsComparison' => $paymentsComparison,
            'paymentsVsComparison' => $paymentsComparison > 0 ? round((($paymentsCurrent - $paymentsComparison) / $paymentsComparison) * 100, 2) : 0,
            'waitPaymentsCurrent' => $waitPaymentsCurrent,
            'failedPaymentsCurrent' => $failedPaymentsCurrent,
            'cashPaymentsCurrent' => $cashPaymentsCurrent,
            'cashWaitPaymentsCurrent' => $cashWaitPaymentsCurrent,
            'cashCompletedPaymentsCurrent' => $cashCompletedPaymentsCurrent,
            // Garder les anciennes clés pour la rétrocompatibilité
            'paymentsToday' => $paymentsCurrent,
            'paymentsYesterday' => $paymentsComparison,
            'paymentsTodayVsYesterday' => $paymentsComparison > 0 ? round((($paymentsCurrent - $paymentsComparison) / $paymentsComparison) * 100, 2) : 0,
            'paymentsThisMonth' => $paymentsCurrent,
            'paymentsLastMonth' => $paymentsComparison,
            'paymentsThisMonthVsLastMonth' => $paymentsComparison > 0 ? round((($paymentsCurrent - $paymentsComparison) / $paymentsComparison) * 100, 2) : 0,
            'waitPaymentsToday' => $waitPaymentsCurrent,
            'waitPaymentsThisMonth' => $waitPaymentsCurrent,
            'failedPaymentsToday' => $failedPaymentsCurrent,
            'failedPaymentsThisMonth' => $failedPaymentsCurrent,
            'cashPaymentsToday' => $cashPaymentsCurrent,
            'cashPaymentsThisMonth' => $cashPaymentsCurrent,
            'cashWaitPaymentsToday' => $cashWaitPaymentsCurrent,
            'cashWaitPaymentsThisMonth' => $cashWaitPaymentsCurrent,
            'cashCompletedPaymentsToday' => $cashCompletedPaymentsCurrent,
            'cashCompletedPaymentsThisMonth' => $cashCompletedPaymentsCurrent,
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

