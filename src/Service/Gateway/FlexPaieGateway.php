<?php

namespace App\Service\Gateway;

use App\Entity\Transaction;
use App\Exception\PaymentException;
use App\Idioma;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\RouterInterface;

class FlexPaieGateway implements GatewayInterface
{
    private array $options = [
        'currency' => 'USD',
        'merchant_phone' => '243899536100',
        'merchant_name' => 'ECOSYS',
        'type' => 1,
    ];

    private string $flexPayToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJcL2xvZ2luIiwicm9sZXMiOlsiTUVSQ0hBTlQiXSwiZXhwIjoxNzg2Mjc5MDc0LCJzdWIiOiI2MTkxMjlhOTA3YjBhZjE2N2MwMjFhOGMwMTc1M2U3MyJ9.wQ4tcSWouxxMaysWN9_M3Ifzs7z3pdzN_jtXjrj1bzA';

    public function __construct(
        private EntityManagerInterface $manager,
        private RouterInterface        $router,
        private HttpClientInterface    $httpClient
    )
    {
    }

    public function setOptions(array $options): void
    {
        $this->options = array_merge($this->options, $options);
    }

    private function getFlexpaieRemoteEndpoint(): string
    {
        return 'https://backend.flexpay.cd/api/rest/v1';
    }

    public function process(Transaction $transaction): array|false
    {
        try {
            return $this->flexPayProcess($transaction, $this->options['type']);
        } catch (\Exception $e) {
            throw new PaymentException('Erreur lors du traitement de la transaction : ' . $e->getMessage());
        }
    }

    /**
     * @throws PaymentException
     */
    public function check(Transaction $transaction): array|false
    {
        $url = $this->getFlexpaieRemoteEndpoint() . '/check/' . $transaction->getProviderReference();

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    sprintf('Authorization: Bearer %s', $this->flexPayToken),
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200)
                throw new PaymentException("Erreur HTTP lors de la vérification de la transaction. Code : $statusCode");

            return $response->toArray();

        } catch (\Exception $e) {
            throw new PaymentException('Erreur lors de la vérification de la transaction : ' . $e->getMessage());
        }
    }

    private function flexPayProcess(Transaction $transaction, int $type): array|false
    {
        $callbackUrl = $this->router->generate('callback_flexpaie', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $request = [
            'merchant' => $this->options['merchant_name'],
            'type' => $type,
            'phone' => $transaction->getPhone(),
            'reference' => $transaction->getReference(),
            'amount' => $transaction->getAmount(),
            'currency' => $transaction->getCurrency()->getMin(),
            'callbackUrl' => $callbackUrl,
        ];

        $url = $this->getFlexpaieRemoteEndpoint() . '/paymentService';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    sprintf('Authorization: Bearer %s', $this->flexPayToken),
                ],
                'json' => $request,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200)
                throw new PaymentException("Erreur HTTP lors du paiement. Code : $statusCode");

            $data = $response->toArray();

            $transaction->setProviderReference($data['orderNumber']);

            if ($data['code'] === "0")
                $transaction->setStatus(Idioma::STATUS_PROCESS);
            else
                $transaction->setStatus(Idioma::STATUS_ERROR);

            $transaction->setMessage($data['message']);

            $transaction->setRespondedAt(new \DateTimeImmutable());

            $this->manager->persist($transaction);
            $this->manager->flush();

            return $data;

        } catch (\Exception $e) {
            throw new PaymentException('Erreur lors de la communication avec le service de paiement : ' . $e->getMessage());
        }
    }

    public function support(): array
    {
        return [
            Transaction::OPERATOR_MOBILE
        ];
    }
}