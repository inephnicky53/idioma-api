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
 * FlexPay card payment (Visa/MasterCard) — Payment Service V2 (hosted redirect).
 * We never collect raw card numbers ourselves: the buyer enters them on
 * FlexPay's own hosted page, we just generate/redirect to that page's URL.
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
    )
    {
    }

    public function process(Transaction $transaction): array
    {
        $reference = $transaction->getReference();
        $callbackUrl = $this->router->generate('callback_flexpaie', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $confirmationUrl = "{$this->frontendUrl}/checkout/confirmation?provider=flexpay-card&transactionId={$transaction->getId()}";

        $request = [
            'authorization' => "Bearer {$this->flexPayToken}",
            'merchant' => $this->merchantName,
            'reference' => $reference,
            'amount' => (string) $transaction->getAmount(),
            'currency' => $transaction->getCurrency()?->getMin() ?? 'USD',
            'description' => "Paiement {$this->merchantName} — {$reference}",
            'callback_url' => $callbackUrl,
            'approve_url' => $confirmationUrl,
            'cancel_url' => "{$confirmationUrl}&cancelled=1",
            'decline_url' => "{$confirmationUrl}&declined=1",
        ];

        try {
            $response = $this->httpClient->request('POST', "{$this->flexPayCardEndpoint}/v2/pay", [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $request,
            ]);

            $data = $response->toArray(false);

            if (($data['code'] ?? null) !== '0' || empty($data['url'])) {
                $transaction->setStatus(Idioma::STATUS_ERROR);
                $transaction->setMessage($data['message'] ?? 'Erreur FlexPay Card');
                $this->manager->persist($transaction);
                $this->manager->flush();
                throw new PaymentException($data['message'] ?? 'Le paiement par carte a échoué.');
            }

            $transaction->setProviderReference($data['orderNumber'] ?? null);
            $transaction->setStatus(Idioma::STATUS_PROCESS);
            $transaction->setMessage($data['message'] ?? null);
            $transaction->setRespondedAt(new \DateTimeImmutable());
            $this->manager->persist($transaction);
            $this->manager->flush();

            return ['approval_url' => $data['url'], 'orderNumber' => $data['orderNumber'] ?? null];
        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PaymentException('Erreur lors de la communication avec FlexPay Card : ' . $e->getMessage());
        }
    }

    /**
     * @throws PaymentException
     */
    public function check(Transaction $transaction): array|false
    {
        $url = $this->flexPayEndpoint . '/check/' . $transaction->getProviderReference();

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    sprintf('Authorization: Bearer %s', $this->flexPayToken),
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new PaymentException('Erreur HTTP lors de la vérification du paiement par carte.');
            }

            return $response->toArray();
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
