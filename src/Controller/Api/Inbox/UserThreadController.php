<?php

namespace App\Controller\Api\Inbox;

use App\ApiResource\CreateTransactionRessouce;
use App\Entity\Course;
use App\Entity\Order;
use App\Entity\Rate;
use App\Entity\Teacher;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\UserCourse;
use App\Entity\UserTeacher;
use App\Idioma;
use App\Repository\CurrencyRepository;
use App\Repository\InboxThreadRepository;
use App\Repository\TransactionRepository;
use App\Service\OperatorProcess;
use App\Service\RateService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserThreadController extends AbstractController
{

    public function __construct()
    {
    }

    public function __invoke(
        Request                   $request,
        CreateTransactionRessouce $data,
        TransactionRepository     $transactionRepository,
        CurrencyRepository        $currencyRepository,
        InboxThreadRepository     $threadRepository,
        RateService               $rateService
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
