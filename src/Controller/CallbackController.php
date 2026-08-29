<?php

namespace App\Controller;

use App\Repository\TransactionRepository;
use App\Service\Transaction\TransactionManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CallbackController extends AbstractController
{
    public function __construct(
        private readonly TransactionRepository    $transactionRepository,
        private readonly TransactionManager       $manager,
        private readonly EntityManagerInterface   $em,
        private readonly LoggerInterface          $logger,
    ) {
    }

    /**
     * FlexPay webhook (mobile money + card). Public HTTPS endpoint.
     * Body may be JSON or application/x-www-form-urlencoded.
     *
     * Treated as a notification only — the payment status is always re-read
     * from FlexPay before anything is credited (see below).
     */
    #[Route('/callback/flexpaie', name: 'callback_flexpaie', methods: ['POST', 'GET'])]
    public function flexpaie(Request $request): JsonResponse
    {
        $body = $this->payload($request);

        $reference = $body['reference'] ?? $body['merchant_reference'] ?? null;
        $code = $body['code'] ?? $body['transaction']['status'] ?? null;

        if ($reference === null || $code === null) {
            $this->logger->warning('FlexPay callback missing fields', ['body' => $body]);

            return new JsonResponse(['message' => 'données manquantes'], Response::HTTP_BAD_REQUEST);
        }

        $transaction = $this->transactionRepository->findOneBy(['reference' => (string) $reference]);
        if (!$transaction) {
            $orderNumber = $body['orderNumber'] ?? $body['provider_reference'] ?? null;
            if ($orderNumber) {
                $transaction = $this->transactionRepository->findOneBy(['providerReference' => (string) $orderNumber]);
            }
        }

        if (!$transaction) {
            $this->logger->warning('FlexPay callback transaction not found', ['reference' => $reference]);

            return new JsonResponse(['message' => 'Transaction non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Only `orderNumber` addresses /check/{orderNumber}. `provider_reference`
        // is the mobile operator's own reference (M-Pesa, Airtel…); storing it
        // here overwrote the one identifier we need to verify the payment.
        $orderNumber = $body['orderNumber'] ?? null;
        if ($orderNumber && !$transaction->getProviderReference()) {
            $transaction->setProviderReference((string) $orderNumber);
        }

        $transaction->setMessage($body['message'] ?? $transaction->getMessage());
        $this->em->flush();

        try {
            // The body is a notification, not proof of payment: this endpoint is
            // public and FlexPay signs nothing, so anyone who guesses a reference
            // could POST {"reference":"…","code":"0"} and unlock a paid course.
            // check() re-reads the status from FlexPay and credits idempotently.
            $this->manager->check($transaction->getId());
        } catch (\Throwable $e) {
            $this->logger->error('FlexPay callback verification failed', [
                'reference' => $transaction->getReference(),
                'transactionId' => $transaction->getId(),
                'claimedCode' => $code,
                'exception' => $e,
            ]);

            // 5xx so FlexPay retries instead of considering us notified.
            return new JsonResponse(
                ['message' => 'Vérification de la transaction impossible'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        return new JsonResponse(['message' => 'Opération réussie']);
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $json = json_decode($request->getContent() ?: '', true);
        if (is_array($json) && $json !== []) {
            return $json;
        }

        $post = $request->request->all();
        if ($post !== []) {
            return $post;
        }

        return $request->query->all();
    }
}
