<?php

namespace App\Controller\Api\Course;

use App\ApiResource\CreateTransactionRessouce;
use App\Entity\Course;
use App\Entity\Order;
use App\Entity\OrderTeacher;
use App\Entity\Teacher;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\UserCourse;
use App\Exception\PaymentException;
use App\Idioma;
use App\Repository\CurrencyRepository;
use App\Repository\TransactionRepository;
use App\Service\OperatorProcess;
use App\Service\RateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiCreateTransactionController extends AbstractController
{

    public function __construct(
        private readonly OperatorProcess $operatorProcess
    )
    {
    }

    /**
     * @throws PaymentException
     */
    public function __invoke(
        Request                   $request,
        CreateTransactionRessouce $data,
        TransactionRepository     $transactionRepository,
        CurrencyRepository        $currencyRepository,
        EntityManagerInterface    $entityManager,
        RateService               $rateService
    ): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $currency = $currencyRepository->findOneBy(['min' => $data->currency]);
        $rate = $rateService->find($currency);
        $orderRepository = $entityManager->getRepository(Order::class);

        $amount = 0;
        $order = new Order();
        $order
            ->setCurrency($currency)
            ->setUser($user)
            ->setStatus(Idioma::STATUS_CREATED);

        if ($data->order_id > 0) {
            $order = $orderRepository->findBy(['id' => $data->order_id, 'status' => Idioma::STATUS_CREATED]);
        } elseif (count($data->teachers) > 0) {
            $teacherRepository = $entityManager->getRepository(Teacher::class);
            foreach ($data->teachers as $t) {
                $hours = $t['hours'];
                if (!is_int($hours))
                    $this->json(["status" => false, "message" => "L'heure n'est pas un entier"], Response::HTTP_FORBIDDEN);

                $teacher = $teacherRepository->find($t['id']);
                if ($teacher) {
                    $orderTeacher = new OrderTeacher();
                    $orderTeacher
                        ->setHours($hours)
                        ->setTeacher($teacher);
                    $order->addOrderTeacher($orderTeacher);
                    $amount += $rateService->resolveAmount($teacher->getPrice(), $teacher->getCurrency(), $rate) * $hours;
                }
                else
                    $this->json(["status" => false, "message" => "Un professeur n'existe pas"], Response::HTTP_FORBIDDEN);
            }
            $order->setAmount($amount);

            $entityManager->persist($order);
            $entityManager->flush();
            /*else {
                    if ($userTeacher = $userTeacherRepository->findOneBy(['teacher' => $teacher->getId(), 'user' => $user])) {
                        $userTeacher->addHours($t['hours']);
                        dd($userTeacher);
                    } else {
                        $userTeacher = new UserTeacher();
                        $userTeacher
                            ->setTeacher($teacher)
                            ->setHours($t['hours'])
                            ->setUser($user);
                        dd($userTeacher);
                    }
                return $this->json(["status" => false, "message" => "Ce professeur n'existe pas"], Response::HTTP_FORBIDDEN);
            }*/
        } elseif (count($data->courses) > 0) {
            $courseRepository = $entityManager->getRepository(Course::class);
            $courses = $courseRepository->findBy(['id' => array_column($data->courses, "id")]);

            foreach ($courses as $course) {
                $userCourse = new UserCourse();
                $userCourse
                    ->setUser($user)
                    ->setCourse($course)
                    ->setCurrency($currency);
                if ($course->isIsPromote())
                    $a = $rateService->resolveAmount($course->getAmountPromo(), $course->getCurrency(), $rate);
                else
                    $a = $rateService->resolveAmount($course->getAmount(), $course->getCurrency(), $rate);
                $userCourse->setAmount($a);
                $amount += $a;

                $order->addUserCourse($userCourse);
            }
            $order->setAmount($amount);

            $entityManager->persist($order);
            $entityManager->flush();
        }

        if (is_null($order->getId())) {
            return $this->json(["status" => false, "message" => "Pas de commande"], Response::HTTP_FORBIDDEN);
        }

        $transaction = new Transaction();
        $transaction->setUser($user);
        $transaction->setAmount($amount);
        $transaction->setCurrency($currency);
        $transaction->setPhone($data->phone);
        $transaction->setCommand($order);
        $transaction->setStatus(Idioma::STATUS_CREATED);
        $transaction->setOperator(OperatorProcess::PAYIN_MOB);

        $this->operatorProcess->setOptions(['operator' => OperatorProcess::PAYIN_MOB]);
        $result = $this->operatorProcess->process($transaction);
        if ($result) {
            $transaction->setProviderReference($result['orderNumber']);
            if ($result['code'] === "0") {
                $order->setStatus(Idioma::STATUS_PROCESS);
                //
                $transaction->setStatus(Idioma::STATUS_WAIT);
                $transaction->setMessage($result['message']); //info
                $json = $this->json(['status' => true, "message" => "Transaction créée"], Response::HTTP_OK);
            } else {
                $order->setStatus(Idioma::STATUS_FAILED);
                $transaction->setStatus(Idioma::STATUS_FAILED);
                $transaction->setMessage($result['message']);
                $json = $this->json(['status' => false, "message" => $result['message']], Response::HTTP_OK);
            }

        } else {
            $order->setStatus(Idioma::STATUS_FAILED);
            $transaction->setStatus(Idioma::STATUS_ERROR);
            $transaction->setMessage('Connection error');
            $json = $this->json(['status' => false, "message" => "Connection error"], Response::HTTP_OK);
        }
        $entityManager->persist($transaction);
        $entityManager->flush();

        return $json;
    }
}
