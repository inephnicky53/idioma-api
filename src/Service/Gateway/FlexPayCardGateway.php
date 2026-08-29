<?php

namespace App\Service\Gateway;

use App\Entity\Transaction;
use App\Exception\PaymentException;
use App\Idioma;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * FlexPay card payment (Visa/MasterCard) — hosted VPOS page.
 *
 * We never collect raw card numbers: the buyer is redirected to FlexPay.
 * Final status arrives asynchronously on callbackUrl; we also poll /check.
 *
 * @see POST {FLEXPAY_CARD_ENDPOINT}/api/rest/v1/vpos/ask
 */
class FlexPayCardGateway implements GatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly RouterInterface        $router,
        private readonly HttpClientInterface    $httpClient,
        private readonly string                 $flexPayToken,
        private readonly string                 $flexPayEndpoint,
        private readonly string                 $flexPayCardEndpoint,
        private readonly string                 $merchantName,
        private readonly string                 $frontendUrl,
    ) {
    }

    public function process(Transaction $transaction): array
    {
        $reference = $transaction->getReference();
        $callbackUrl = $this->router->generate('callback_flexpaie', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $frontend = rtrim($this->frontendUrl, '/');
        $confirmationUrl = "{$frontend}/checkout/confirmation?provider=flexpay-card&transactionId={$transaction->getId()}";

        $request = [
            'authorization' => $this->flexPayToken,
            'merchant' => $this->merchantName,
            'reference' => $reference,
            'amount' => round((float) $transaction->getAmount(), 2),
            'currency' => $transaction->getCurrency()?->getMin() ?? 'USD',
            'description' => "Paiement {$this->merchantName} — {$reference}",
            'callback_url' => $callbackUrl,
            'approve_url' => $confirmationUrl,
            'cancel_url' => "{$confirmationUrl}&cancelled=1",
            'decline_url' => "{$confirmationUrl}&declined=1",
            'home_url' => $frontend,
        ];

        $urls = [
            rtrim($this->flexPayCardEndpoint, '/') . '/api/rest/v1/vpos/ask',
            rtrim($this->flexPayCardEndpoint, '/') . '/v2/pay',
        ];

        $lastError = null;
        foreach ($urls as $url) {
            try {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => FlexPayClient::authHeaders($this->flexPayToken),
                    'json' => $request,
                ]);

                $data = $response->toArray(false);
                $hostedUrl = $data['url'] ?? $data['payment_url'] ?? $data['redirect_url'] ?? null;

                if (!FlexPayClient::isSuccessCode($data['code'] ?? null) || empty($hostedUrl)) {
                    $lastError = $data['message'] ?? 'Erreur FlexPay Card';
                    if ($response->getStatusCode() === 404) {
                        continue;
                    }
                    $transaction->setStatus(Idioma::STATUS_ERROR);
                    $transaction->setMessage($lastError);
                    $this->manager->persist($transaction);
                    $this->manager->flush();
                    throw new PaymentException($lastError);
                }

                if (!empty($data['orderNumber'])) {
                    $transaction->setProviderReference((string) $data['orderNumber']);
                }
                $transaction->setStatus(Idioma::STATUS_PROCESS);
                $transaction->setMessage($data['message'] ?? null);
                $transaction->setRespondedAt(new \DateTimeImmutable());
                $this->manager->persist($transaction);
                $this->manager->flush();

                return [
                    'approval_url' => $hostedUrl,
                    'orderNumber' => $data['orderNumber'] ?? null,
                    'async' => true,
                ];
            } catch (PaymentException $e) {
                throw $e;
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                continue;
            }
        }

        throw new PaymentException($lastError ?: 'Le paiement par carte a échoué.');
    }

    /**
     * Card status is checked on the same mobile-money backend /check/{orderNumber}.
     *
     * @throws PaymentException
     */
    public function check(Transaction $transaction): array|false
    {
        $orderNumber = $transaction->getProviderReference();
        if (!$orderNumber) {
            throw new PaymentException('Référence FlexPay manquante pour cette transaction.');
        }

        $url = rtrim($this->flexPayEndpoint, '/') . '/check/' . rawurlencode($orderNumber);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => FlexPayClient::authHeaders($this->flexPayToken),
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new PaymentException('Erreur HTTP lors de la vérification du paiement par carte.');
            }

            return $response->toArray(false);
        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PaymentException('Erreur lors de la vérification du paiement par carte : ' . $e->getMessage());
        }
    }

    public function support(): array
    {
        return [
            Transaction::OPERATOR_BANK,
        ];
    }
}
