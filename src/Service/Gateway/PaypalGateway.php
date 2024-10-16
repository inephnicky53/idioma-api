<?php

namespace App\Service\Gateway;

use App\Entity\Transaction as TransactionEntity;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalHttp\HttpResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PaypalGateway implements GatewayInterface
{
    private SandboxEnvironment $environment;
    private PayPalHttpClient $client;

    public function __construct()
    {
        $this->environment = new SandboxEnvironment($_ENV['PAYPAL_CLIENT_ID'], $_ENV['PAYPAL_CLIENT_SECRET']);
        $this->client = new PayPalHttpClient($this->environment);
    }

    public function process(TransactionEntity $transaction): JsonResponse
    {
        $reference = $transaction->getReference();
        $total = $transaction->getAmount();
        $currency = $transaction->getCurrency()->getMin();
        $returnUrl = "https://api.idioma.international/checkout/success";
        $cancelUrl = "https://api.idioma.international/checkout/cancel";

        try {
            $payment = $this->createPayment($total, $currency, $returnUrl, $cancelUrl, $reference);
            $approvalUrl = $payment->result->links[1]->href;

            return new JsonResponse(['approval_url' => $approvalUrl], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Crée la demande de paiement avec PayPal
     *
     * @throws \Exception
     */
    public function createPayment($total, $currency, $returnUrl, $cancelUrl, $reference): HttpResponse
    {
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = [
            "intent" => "CAPTURE",
            "purchase_units" => [[
                "amount" => [
                    "currency_code" => $currency,
                    "value" => "100"
                ],
                "description" => "Paiement via PayPal de la commande n° $reference"
            ]],
            "application_context" => [
                "cancel_url" => $cancelUrl,
                "return_url" => $returnUrl
            ]
        ];

        try {
            return $this->client->execute($request);
        } catch (\Exception $e) {
            throw new \Exception("Erreur lors de la création du paiement : " . $e->getMessage());
        }
    }

    /**
     * Détermine si ce service supporte l'opérateur PayPal
     */
    public function support(): array
    {
        return [
            TransactionEntity::OPERATOR_PAYPAL
        ];
    }
}