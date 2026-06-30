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
use Symfony\Contracts\HttpClient\ResponseInterface;

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

        // Sans jeton FlexPay, l'API renvoie une page d'erreur HTML : on échoue
        // proprement plutôt que de laisser le décodeur JSON planter plus loin.
        if (empty($this->flexpayToken)) {
            $payment->setStatus(PaymentStatus::ERROR);
            $payment->setNotes('Paiement momentanément indisponible (configuration FlexPay manquante).');
            $this->recordRawError($payment, 'flexpay_error', 0, 'FLEXPAY_TOKEN manquant');
            $this->manager->flush();
            return $payment;
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

            [$data, $rawBody] = $this->decodeFlexPayResponse($response);
            $statusCode = $response->getStatusCode();

            if ($data === null) {
                // Corps non-JSON (page d'erreur HTML, etc.).
                $payment->setStatus(PaymentStatus::ERROR);
                $payment->setNotes('Paiement mobile momentanément indisponible. Merci de réessayer plus tard.');
                $this->recordRawError($payment, 'flexpay_error', $statusCode, $rawBody);
            } elseif ($statusCode === Response::HTTP_OK && ($data['code'] ?? '') === "0") {
                if (isset($data['orderNumber'])) {
                    $payment->setProviderReference($data['orderNumber']);
                }
                $payment->setStatus(PaymentStatus::WAIT);
            } else {
                if (isset($data['orderNumber'])) {
                    $payment->setProviderReference($data['orderNumber']);
                }
                $payment->setStatus(PaymentStatus::ERROR);
                $payment->setNotes($data['message'] ?? ('Erreur FlexPay (HTTP ' . $statusCode . ')'));
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
     * Prépare un paiement par carte.
     *
     * L'API carte FlexPay (/v2/pay) du marchand ne renvoie pas le JSON `{url}`
     * documenté : elle attend un POST `application/x-www-form-urlencoded` et
     * rend directement sa page hébergée de saisie de carte. On ne peut donc pas
     * appeler l'API côté serveur pour récupérer une URL : le navigateur du client
     * doit POSTer ces champs vers FlexPay.
     *
     * On génère donc un nonce à usage unique et on expose une `paymentUrl` qui
     * pointe vers notre propre endpoint de redirection ({@see CardRedirectController}).
     * Cet endpoint renvoie un mini-formulaire auto-soumis vers FlexPay, ce qui
     * garde le jeton marchand côté serveur (jamais dans le bundle JS ni le JSON).
     */
    private function createCardTransaction(Payment $payment, array $options): Payment
    {
        try {
            $nonce = bin2hex(random_bytes(16));

            $paymentData = $payment->getData() ?? [];
            $paymentData['cardRedirectNonce'] = $nonce;
            if (isset($options['description'])) {
                $paymentData['cardDescription'] = $options['description'];
            }
            // URL (sur notre backend) vers laquelle le frontend redirige le navigateur.
            $paymentData['paymentUrl'] = $this->router->generate(
                'flexpay_card_redirect',
                ['reference' => $payment->getReference(), 'nonce' => $nonce],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $payment->setData($paymentData);

            $payment->setStatus(PaymentStatus::WAIT);
        } catch (\Throwable $e) {
            $payment->setStatus(PaymentStatus::ERROR);
            $payment->setNotes('Paiement par carte momentanément indisponible. Merci de réessayer plus tard.');
            $this->recordRawError($payment, 'flexpay_card_error', 0, $e->getMessage());
        }

        return $payment;
    }

    /**
     * Construit l'action et les champs du formulaire à POSTer (form-encoded) vers
     * la page de paiement carte hébergée par FlexPay. Utilisé par l'endpoint de
     * redirection — le jeton marchand reste ainsi côté serveur.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    public function buildCardFormParams(Payment $payment): array
    {
        $confirmationBase = rtrim((string) $this->frontendUrl, '/') . '/payment/confirmation';
        $reference = (string) $payment->getReference();
        $confirmationUrl = static fn (string $status): string => $confirmationBase . '?' . http_build_query([
            'reference' => $reference,
            'status' => $status,
        ]);

        $description = $payment->getData()['cardDescription'] ?? 'Paiement Idioma International';

        return [
            'action' => rtrim((string) $this->flexpayCardEndpoint, '/') . '/v2/pay',
            'fields' => [
                'authorization' => 'Bearer ' . $this->flexpayToken,
                'merchant' => (string) $this->merchantName,
                'reference' => $reference,
                'amount' => (string) $payment->getAmount(),
                'currency' => $payment->getCurrency()?->value ?? 'USD',
                'description' => (string) $description,
                'callback_url' => $this->router->generate(
                    'callback_flexpay', [],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'approve_url' => $confirmationUrl('approved'),
                'cancel_url' => $confirmationUrl('cancelled'),
                'decline_url' => $confirmationUrl('declined'),
            ],
        ];
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

    /**
     * Décode le corps d'une réponse FlexPay sans laisser remonter une erreur de
     * décodage : FlexPay renvoie parfois une page HTML (statut 200) au lieu du
     * JSON attendu. Retourne [donnéesDécodées|null, corpsBrut].
     *
     * @return array{0: array<string,mixed>|null, 1: string}
     */
    private function decodeFlexPayResponse(ResponseInterface $response): array
    {
        try {
            $content = $response->getContent(false);
        } catch (\Throwable) {
            $content = '';
        }

        $data = json_decode($content, true);

        return [is_array($data) ? $data : null, $content];
    }

    /**
     * Conserve une trace technique de l'échec dans le champ `data` du paiement
     * (le champ `notes`, lui, reste un message lisible par l'utilisateur).
     */
    private function recordRawError(Payment $payment, string $key, int $httpStatus, string $detail): void
    {
        $paymentData = $payment->getData() ?? [];
        $paymentData[$key] = [
            'httpStatus' => $httpStatus,
            'detail' => mb_substr($detail, 0, 300),
        ];
        $payment->setData($paymentData);
    }

    public function getName(): string
    {
        return 'flexpay';
    }
}
