<?php

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Enum\PaymentMethod;
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
        private ?string                $flexpayCardEndpoint = null,
        private ?string                $merchantName = null,
        private ?string                $frontendUrl = null
    ) {}

    /**
     * Envoie la transaction à FlexPay et met à jour le Payment existant
     *
     * @param $payment Le paiement déjà créé par PaymentManager
     * @param int $type Type de transaction (1=MOBILE, 2=BANK/CARD)
     * @param array $options Options additionnelles (phone, etc.)
     */
    public function createTransaction($payment, int $type, array $options): Payment
    {
        if (!$payment instanceof Payment) {
            throw new InvalidArgumentException('Le premier argument doit être une instance de Payment');
        }

        try {
            // Check if it's a card payment (type=2 or payment method CARD)
            if (
                $type === 2 ||
                $payment->getPaymentMethod() === PaymentMethod::CARD
            ) {
                $this->createCardTransaction($payment, $options);
                $this->manager->flush();
                return $payment;
            }

            $request = [
                "merchant" => $this->merchantName,
                "type" => $type,
                "phone" => $options['phone'] ?? $payment->getPhone(),
                "reference" => $payment->getReference(),
                "amount" => $payment->getAmount(),
                "currency" => $payment->getCurrency()?->value ?? 'USD',
                "callbackUrl" => $this->router->generate(
                    'callback_flexpay', [],
                    UrlGeneratorInterface::ABSOLUTE_URL
                )
            ];

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
                    $payment->setProviderReference($data['orderNumber']);
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
        } catch (TransportExceptionInterface $e) {
            $payment->setStatus(PaymentStatus::ERROR);
            $payment->setNotes('FlexPay connection error: ' . $e->getMessage());
        } catch (\Exception $e) {
            $payment->setStatus(PaymentStatus::ERROR);
            $payment->setNotes('FlexPay unexpected error: ' . $e->getMessage());
        }

        $this->manager->flush();
        return $payment;
    }

    /**
     * Creates a card payment transaction using FlexPay Card API (/v2/pay)
     */
    private function createCardTransaction(Payment $payment, array $options): Payment
    {
        $description = $options['description'] ?? 'Paiement Idioma International';

        // URLs de redirection vers la page de confirmation du frontend.
        // FlexPay redirige l'utilisateur vers l'une d'elles selon l'issue du
        // paiement par carte (approuvé / annulé / refusé).
        $confirmationBase = rtrim((string) $this->frontendUrl, '/') . '/payment/confirmation';
        $reference = $payment->getReference();
        $confirmationUrl = static function (string $status) use ($confirmationBase, $reference): string {
            return $confirmationBase . '?' . http_build_query([
                'reference' => $reference,
                'status' => $status,
            ]);
        };

        $request = [
            // L'API carte FlexPay (/v2/pay) attend le token dans le corps JSON
            // (champ "authorization"), en plus de l'en-tête HTTP. Cf. documentation V2.
            "authorization" => "Bearer " . $this->flexpayToken,
            "merchant" => $this->merchantName,
            "reference" => $payment->getReference(),
            "amount" => $payment->getAmount(),
            "currency" => $payment->getCurrency()?->value ?? 'USD',
            "description" => $description,
            "callback_url" => $this->router->generate(
                'callback_flexpay', [],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            "approve_url" => $options['approve_url'] ?? $confirmationUrl('approved'),
            "cancel_url" => $options['cancel_url'] ?? $confirmationUrl('cancelled'),
            "decline_url" => $options['decline_url'] ?? $confirmationUrl('declined')
        ];

        try {
            $response = $this->httpClient->request('POST', $this->flexpayCardEndpoint . '/v2/pay', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->flexpayToken,
                ],
                'json' => $request,
                'timeout' => 30
            ]);

            if ($response->getStatusCode() === Response::HTTP_OK) {
                $data = $response->toArray();

                if (isset($data['orderNumber'])) {
                    $payment->setProviderReference($data['orderNumber']);
                }

                if (($data['code'] ?? '') === "0") {
                    $payment->setStatus(PaymentStatus::WAIT);
                    // Store the payment URL on the payment entity for the frontend to redirect to.
                    // Clé `paymentUrl` (camelCase) pour correspondre au type PaymentData côté frontend.
                    if (isset($data['url'])) {
                        $paymentData = $payment->getData() ?? [];
                        $paymentData['paymentUrl'] = $data['url'];
                        $payment->setData($paymentData);
                    }
                } else {
                    $payment->setStatus(PaymentStatus::ERROR);
                    $payment->setNotes($data['message'] ?? 'Erreur FlexPay Card');
                }
            } else {
                $payment->setStatus(PaymentStatus::ERROR);
                $payment->setNotes('FlexPay Card HTTP Error: ' . $response->getStatusCode());
            }
        } catch (TransportExceptionInterface $e) {
            $payment->setStatus(PaymentStatus::ERROR);
            $payment->setNotes('FlexPay Card connection error: ' . $e->getMessage());
        } catch (\Exception $e) {
            $payment->setStatus(PaymentStatus::ERROR);
            $payment->setNotes('FlexPay Card unexpected error: ' . $e->getMessage());
        }

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
        if (!$payment->getProviderReference()) {
            return false;
        }

        $response = $this->httpClient->request('GET', $this->flexpayEndpoint . '/check/' . $payment->getProviderReference(), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->flexpayToken
            ],
        ]);

        $data = $response->toArray();

        // Store check transaction details in payment data
        $paymentData = $payment->getData() ?? [];
        $paymentData['flexpay_check'] = $data;
        $payment->setData($paymentData);

        if (isset($data['code']) && $data['code'] === "0" && isset($data['transaction'])) {
            $transaction = $data['transaction'];
            $statusCode = $transaction['status'] ?? null;
            if ($statusCode !== null) {
                $newStatus = PaymentStatus::fromFlexPayCode((string) $statusCode);
                $payment->setStatus($newStatus);
                if (isset($data['message'])) {
                    $existingNotes = $payment->getNotes() ?? '';
                    $payment->setNotes(trim($existingNotes . "\nFlexPay Check: " . $data['message']));
                }
            }
        }

        $this->manager->flush();
        return $data;
    }

    public function getName(): string
    {
        return 'flexpay';
    }
}
