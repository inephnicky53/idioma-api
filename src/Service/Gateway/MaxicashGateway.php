<?php

namespace App\Service\Gateway;

use App\Entity\Transaction;
use App\Service\GeoIP;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\HttpClient;

class MaxicashGateway implements GatewayInterface
{
    const OPERATORS = [
        "MAXICAH_DRC"   => 0,
        "AIRTEL_DRC"    => 1,
        "VODACOM_DRC"   => 2,
        "ORANGE_DRC"    => 3,
    ];
    public function __construct(
        private $app_id,
        private $app_pwd,
    )
    {
    }

    public function process($transaction)
    {
        $order = $transaction->getCommand();

        $clientHttp = HttpClient::create();
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        #$uri = 'https://webapi.maxicashapp.com';
        $uri = 'https://webapi-test.maxicashapp.com';

        $currency = $transaction->getCurrency();
        $payment_phone = $transaction->getPhone();

        $operator = self::OPERATORS[GeoIP::phoneOperator($payment_phone, 'CD')];

        $amount = $order->getAmount() * 100;
        $body = <<<BODY
{
"RequestData": {
    "Amount": "$amount",
    "Reference": "{$transaction->getReference()}",
    "Telephone": "$payment_phone"
  },
  "MerchantID": "{$this->app_id}",
  "MerchantPassword": "{$this->app_pwd}",
  "PayType": $operator,
  "CurrencyCode": "$currency"
}
BODY;
        try {
            $response = $clientHttp->request('POST', $uri.'/Integration/PayNowSync', [
                'headers' => array_merge($headers, []),
                'body' => $body
            ]);
            return json_decode($response->getContent(), true);
        } catch (ClientException $exception) {
            return [
                'status' => $exception->getCode(),
                'message' => $exception->getMessage()
            ];
        }
    }

    public function support(): array
    {
        return [
            Transaction::OPERATOR_MOBILE
        ];
    }
}