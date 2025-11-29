<?php

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class FlexPayProvider implements PaymentProviderInterface
{
    public function __construct(
        private EntityManagerInterface $manager,
        private RouterInterface        $router,
        private HttpClientInterface    $httpClient,
        private ?string                $flexpayToken = null,
        private ?string                $flexpayEndpoint = null,
        private ?string                $merchantName = null
    ) {}

    /**
     * Envoie la transaction à FlexPay et met à jour le Payment existant
     *
     * @param mixed $payment Le paiement déjà créé par PaymentManager
     * @param int $type Type de transaction (1=MOBILE, 2=BANK)
     * @param array $options Options additionnelles (phone, etc.)
     */
    public function createTransaction(mixed $payment, int $type, array $options): Payment
    {
        if (!$payment instanceof Payment) {
            throw new InvalidArgumentException('Le premier argument doit être une instance de Payment');
        }

        $request = [
            "merchant" => $this->merchantName,
            "type" => $type,
            "phone" => $options['phone'] ?? $payment->getPhone(),
            "reference" => $payment->getTransactionId() ?? strtoupper(uniqid()),
            "amount" => $payment->getAmount(),
            "currency" => $payment->getCurrency()?->value ?? 'USD',
            "callbackUrl" => $this->router->generate(
                'callback_flexpay', [],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
        ];

        try {
            $response = $this->httpClient->request('POST', $this->flexpayEndpoint . '/paymentService', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->flexpayToken
                ],
                'json' => $request,
                'timeout' => 30
            ]);

            if ($response->getStatusCode() === Response::HTTP_OK) {
                $data = $response->toArray();

                // Mettre à jour le transactionId avec celui de FlexPay
                if (isset($data['orderNumber'])) {
                    $payment->setTransactionId($data['orderNumber']);
                }

                if (($data['code'] ?? '') === "0") {
                    $payment->setStatus(PaymentStatus::WAIT);
                } else {
                    $payment->setStatus(PaymentStatus::ERROR);
                    $payment->setNotes($data['message'] ?? 'Erreur FlexPay');
                }
            } else {
                $payment->setStatus(PaymentStatus::ERROR);
                $payment->setNotes('FlexPay HTTP Error: ' . $response->getStatusCode());
            }
        } catch (\Exception $e) {
            $payment->setStatus(PaymentStatus::ERROR);
            $payment->setNotes('FlexPay connection error: ' . $e->getMessage());
        }

        $this->manager->flush();
        return $payment;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function checkTransaction(Payment $payment): array|bool
    {
        if (!$payment->getTransactionId()) {
            return false;
        }

        $response = $this->httpClient->request('GET', $this->flexpayEndpoint . '/check/' . $payment->getTransactionId());

        return $response->toArray();
    }

    public function getName(): string
    {
        return 'flexpay';
    }
}
