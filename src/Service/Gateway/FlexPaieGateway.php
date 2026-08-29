<?php

namespace App\Service\Gateway;

use App\Entity\Transaction;
use App\Exception\PaymentException;
use App\Idioma;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FlexPaieGateway implements GatewayInterface
{
    private array $options = [
        'currency' => 'USD',
        'type' => 1,
    ];

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly RouterInterface        $router,
        private readonly HttpClientInterface    $httpClient,
        private readonly string                 $flexPayToken,
        private readonly string                 $flexPayEndpoint,
        private readonly string                 $merchantName,
    ) {
    }

    public function setOptions(array $options): void
    {
        $this->options = array_merge($this->options, $options);
    }

    public function process(Transaction $transaction): array|false
    {
        try {
            return $this->flexPayProcess($transaction, (int) $this->options['type']);
        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PaymentException('Erreur lors du traitement de la transaction : ' . $e->getMessage());
        }
    }

    /**
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

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw new PaymentException("Erreur HTTP lors de la vérification de la transaction. Code : $statusCode");
            }

            return $response->toArray(false);
        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PaymentException('Erreur lors de la vérification de la transaction : ' . $e->getMessage());
        }
    }

    /**
     * Mobile money is asynchronous: FlexPay sends a USSD push to the phone.
     * We persist STATUS_PROCESS and return immediately; fulfillment happens
     * via callback or GET /check/{orderNumber}.
     *
     * @throws PaymentException
     */
    private function flexPayProcess(Transaction $transaction, int $type): array|false
    {
        $callbackUrl = $this->router->generate('callback_flexpaie', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $phone = FlexPayClient::formatPhone($transaction->getPhone());
        if ($phone === '') {
            throw new PaymentException('Numéro de téléphone Mobile Money invalide.');
        }

        $transaction->setPhone($phone);

        $request = [
            'merchant' => $this->merchantName,
            'type' => $type,
            'phone' => $phone,
            'reference' => $transaction->getReference(),
            'amount' => round((float) $transaction->getAmount(), 2),
            'currency' => $transaction->getCurrency()?->getMin() ?? 'USD',
            'callbackUrl' => $callbackUrl,
            'description' => sprintf('Paiement %s — %s', $this->merchantName, $transaction->getReference()),
        ];

        $url = rtrim($this->flexPayEndpoint, '/') . '/paymentService';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => FlexPayClient::authHeaders($this->flexPayToken),
                'json' => $request,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($statusCode !== 200) {
                $transaction->setStatus(Idioma::STATUS_ERROR);
                $transaction->setMessage($data['message'] ?? "Erreur HTTP FlexPay ($statusCode)");
                $this->manager->persist($transaction);
                $this->manager->flush();
                throw new PaymentException($data['message'] ?? "Erreur HTTP lors du paiement. Code : $statusCode");
            }

            if (!empty($data['orderNumber'])) {
                $transaction->setProviderReference((string) $data['orderNumber']);
            }

            if (FlexPayClient::isSuccessCode($data['code'] ?? null)) {
                // code 0 = request accepted, NOT payment confirmed.
                $transaction->setStatus(Idioma::STATUS_PROCESS);
            } else {
                $transaction->setStatus(Idioma::STATUS_ERROR);
            }

            $transaction->setMessage($data['message'] ?? null);
            $transaction->setRespondedAt(new \DateTimeImmutable());

            $this->manager->persist($transaction);
            $this->manager->flush();

            return $data;
        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PaymentException('Erreur lors de la communication avec le service de paiement : ' . $e->getMessage());
        }
    }

    public function support(): array
    {
        return [
            Transaction::OPERATOR_MOBILE,
        ];
    }
}
