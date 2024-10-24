<?php

namespace App\Controller\Api\Inbox;

use App\Entity\User;
use App\Repository\CurrencyRepository;
use App\Repository\InboxThreadRepository;
use App\Repository\TransactionRepository;
use App\Service\RateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class UserThreadController extends AbstractController
{
    public function __construct()
    {
    }

    public function __invoke(
        Request $request, $data,
        TransactionRepository $transactionRepository,
        CurrencyRepository    $currencyRepository,
        InboxThreadRepository $threadRepository,
        RateService           $rateService
    ): array
    {
        /** @var User $user */
        $user = $this->getUser();

        $threads = $user->getInboxThreads()->toArray();
        if ($user->getTeacher()) {
            $teacherThreads = $threadRepository->findBy(['teacher' => $user->getTeacher()->getId()]);
            $threads = array_merge($threads, $teacherThreads);
        }

        return array_unique($threads);
    }
}
