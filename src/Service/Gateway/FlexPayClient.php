<?php

namespace App\Service\Gateway;

/**
 * Shared FlexPay (flexpay.cd) helpers.
 *
 * Mobile money is asynchronous: POST /paymentService pushes a USSD prompt,
 * then FlexPay POSTs the result to callbackUrl. Card payments redirect the
 * buyer to a hosted page; the same callbackUrl is used for the final status.
 */
final class FlexPayClient
{
    public static function authHeaders(string $token): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    /**
     * FlexPay expects international format 243XXXXXXXXX (DRC).
     */
    public static function formatPhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '243' . substr($digits, 1);
        }
        if (!str_starts_with($digits, '243') && strlen($digits) === 9) {
            $digits = '243' . $digits;
        }

        return $digits;
    }

    public static function isSuccessCode(mixed $code): bool
    {
        return (string) $code === '0';
    }

    /**
     * Transaction status out of a callback body or a /check response.
     *
     * FlexPay nests it under `transaction` when answering /check but sends it
     * flat in callbacks. Returns '' when absent — callers must not read that
     * as success.
     *
     * @param array<string, mixed> $body
     */
    public static function transactionStatus(array $body): string
    {
        return (string) ($body['transaction']['status'] ?? $body['status'] ?? '');
    }
}
