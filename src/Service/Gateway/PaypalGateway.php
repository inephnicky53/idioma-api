<?php

namespace App\Service\Gateway;

use App\Entity\Transaction as TransactionEntity;
use App\Exception\PaymentException;
use App\Idioma;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * PayPal Orders v2 REST API integration (Checkout with redirect).
 * @see https://developer.paypal.com/docs/api/orders/v2/
 */
class PaypalGateway implements GatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly HttpClientInterface    $httpClient,
        private readonly string                 $clientId,
        private readonly string                 $clientSecret,
        private readonly string                 $mode,
        private readonly string                 $frontendUrl,
    )
    {
    }

    private function getApiBase(): string
    {
        return $this->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * @throws PaymentException
     */
    private function getAccessToken(): string
    {
        try {
            $response = $this->httpClient->request('POST', $this->getApiBase() . '/v1/oauth2/token', [
                'auth_basic' => [$this->clientId, $this->clientSecret],
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => 'grant_type=client_credentials',
            ]);

            $data = $response->toArray();

            return $data['access_token'];
        } catch (\Exception $e) {
            throw new PaymentException('Impossible de s\'authentifier auprès de PayPal : ' . $e->getMessage());
        }
    }

    /**
     * @throws PaymentException
     */
    public function process(TransactionEntity $transaction): array
    {
        $reference = $transaction->getReference();
        $total = number_format($transaction->getAmount(), 2, '.', '');
        $currency = strtoupper($transaction->getCurrency()?->getMin() ?? 'USD');
        $returnUrl = "{$this->frontendUrl}/checkout/confirmation?provider=paypal&transactionId={$transaction->getId()}";
        $cancelUrl = "{$this->frontendUrl}/checkout/confirmation?provider=paypal&transactionId={$transaction->getId()}&cancelled=1";

        try {
            $token = $this->getAccessToken();

            $response = $this->httpClient->request('POST', $this->getApiBase() . '/v2/checkout/orders', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$token}",
                ],
                'json' => [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'reference_id' => $reference,
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => $total,
                        ],
                        'description' => "Paiement via PayPal de la commande n° {$reference}",
                    ]],
                    'application_context' => [
                        'cancel_url' => $cancelUrl,
                        'return_url' => $returnUrl,
                        'user_action' => 'PAY_NOW',
                    ],
                ],
            ]);

            $data = $response->toArray(false);

            $approvalUrl = null;
            foreach ($data['links'] ?? [] as $link) {
                if ($link['rel'] === 'approve') {
                    $approvalUrl = $link['href'];
                    break;
                }
            }

            if (!$approvalUrl) {
                throw new PaymentException('PayPal n\'a retourné aucune URL d\'approbation.');
            }

            $transaction->setProviderReference($data['id']);
            $transaction->setStatus(Idioma::STATUS_PROCESS);
            $transaction->setRespondedAt(new \DateTimeImmutable());
            $this->manager->persist($transaction);
            $this->manager->flush();

            return ['approval_url' => $approvalUrl, 'orderNumber' => $data['id']];
        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            $transaction->setStatus(Idioma::STATUS_ERROR);
            $this->manager->persist($transaction);
            $this->manager->flush();
            throw new PaymentException('Erreur lors de la création du paiement PayPal : ' . $e->getMessage());
        }
    }

    /**
     * Capture the funds for a PayPal order that the buyer has approved.
     *
     * @throws PaymentException
     */
    public function check(TransactionEntity $transaction): array
    {
        $orderId = $transaction->getProviderReference();
        if (!$orderId) {
            throw new PaymentException('Aucune commande PayPal associée à cette transaction.');
        }

        try {
            $token = $this->getAccessToken();

            // An order can only be captured once; if already captured/completed, just report status.
            $orderResponse = $this->httpClient->request('GET', $this->getApiBase() . "/v2/checkout/orders/{$orderId}", [
                'headers' => ['Authorization' => "Bearer {$token}"],
            ]);
            $order = $orderResponse->toArray(false);

            if ($order['status'] === 'COMPLETED') {
                return ['code' => '0', 'transaction' => ['status' => '0']];
            }

            if ($order['status'] !== 'APPROVED') {
                // Not yet approved by the buyer (still on PayPal's page), or voided/expired.
                return ['code' => '0', 'transaction' => ['status' => '2']];
            }

            $captureResponse = $this->httpClient->request('POST', $this->getApiBase() . "/v2/checkout/orders/{$orderId}/capture", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$token}",
                ],
            ]);
            $capture = $captureResponse->toArray(false);

            $status = ($capture['status'] ?? '') === 'COMPLETED' ? '0' : '1';

            return ['code' => '0', 'transaction' => ['status' => $status]];
        } catch (\Exception $e) {
            throw new PaymentException('Erreur lors de la vérification du paiement PayPal : ' . $e->getMessage());
        }
    }

    public function support(): array
    {
        return [
            TransactionEntity::OPERATOR_PAYPAL,
        ];
    }
}
