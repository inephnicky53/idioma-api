<?php

namespace App\Controller;

use App\Idioma;
use App\Repository\TransactionRepository;
use App\Service\Transaction\TransactionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CallbackController extends AbstractController
{
    public function __construct(
        private readonly TransactionRepository  $transactionRepository,
        private readonly TransactionManager     $manager,
        private readonly EntityManagerInterface $em
    )
    {
    }

    /**
     * @throws \Exception
     */
    #[Route('/callback/flexpaie', name: 'callback_flexpaie', methods: ['POST'])]
    public function flexpaie(): JsonResponse
    {
        $body = json_decode(file_get_contents('php://input'), true);

        if (!isset($body['reference'], $body['code'])) {
            return new JsonResponse(['message' => 'données manquantes'], Response::HTTP_BAD_REQUEST);
        }

        $transaction = $this->transactionRepository->findOneBy(['reference' => $body['reference']]);

        if (!$transaction) {
            return new JsonResponse(['message' => 'Transaction non trouvée'], Response::HTTP_NOT_FOUND);
        }

        if ($body['code'] === '0') {
            $transaction->setStatus(Idioma::STATUS_SUCCESS);
            if (isset($body['provider_reference'])) {
                $transaction->setProviderReference($body['provider_reference']);
            }
            $this->manager->confirmTransaction($transaction);
        } else {
            $transaction->setStatus(Idioma::STATUS_ERROR);
        }

        $transaction->setRespondedAt(new \DateTimeImmutable());

        $this->em->persist($transaction);
        $this->em->flush();

        return new JsonResponse(['message' => 'Opération réussie']);
    }
}