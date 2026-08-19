<?php

namespace App\Service\Streaming;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * GetStream Video integration — creates calls and issues user tokens.
 * @see https://getstream.io/video/docs/
 */
class GetStreamService
{
    private const API_BASE = 'https://video.stream-io-api.com/api/v2';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey = null,
        private readonly ?string $apiSecret = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiSecret);
    }

    /**
     * @param list<string> $memberUserIds Stream user ids (stringified app user ids)
     * @return array{callId: string, callType: string, apiKey: string, token: string, userId: string, isHost: bool}
     */
    public function prepareCall(
        string $callId,
        string $callType,
        string $hostUserId,
        array $memberUserIds,
        bool $isHost,
    ): array {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('GetStream is not configured (GETSTREAM_API_KEY / GETSTREAM_API_SECRET).');
        }

        $this->upsertUser($hostUserId);
        foreach ($memberUserIds as $memberId) {
            if ($memberId !== $hostUserId) {
                $this->upsertUser($memberId);
            }
        }

        $members = array_map(
            fn (string $id) => [
                'user_id' => $id,
                'role' => $id === $hostUserId ? 'admin' : 'user',
            ],
            array_values(array_unique(array_merge([$hostUserId], $memberUserIds)))
        );

        $serverToken = $this->createServerToken($hostUserId);
        $response = $this->httpClient->request(
            'POST',
            self::API_BASE . "/video/call/{$callType}/{$callId}?api_key={$this->apiKey}",
            [
                'headers' => [
                    'Authorization' => $serverToken,
                    'Content-Type' => 'application/json',
                    'stream-auth-type' => 'jwt',
                ],
                'json' => [
                    'data' => [
                        'created_by_id' => $hostUserId,
                        'members' => $members,
                    ],
                ],
            ]
        );

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('GetStream call creation failed: ' . $response->getContent(false));
        }

        return [
            'callId' => $callId,
            'callType' => $callType,
            'apiKey' => $this->apiKey,
            'token' => $this->createUserToken($hostUserId),
            'userId' => $hostUserId,
            'isHost' => $isHost,
        ];
    }

    public function createUserToken(string $userId, int $ttlSeconds = 3600): string
    {
        return $this->signJwt([
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + $ttlSeconds,
        ]);
    }

    private function createServerToken(string $userId, int $ttlSeconds = 3600): string
    {
        return $this->signJwt([
            'user_id' => $userId,
            'server' => true,
            'iat' => time(),
            'exp' => time() + $ttlSeconds,
        ]);
    }

    private function upsertUser(string $userId): void
    {
        $serverToken = $this->createServerToken('system');
        $this->httpClient->request(
            'POST',
            self::API_BASE . "/users?api_key={$this->apiKey}",
            [
                'headers' => [
                    'Authorization' => $serverToken,
                    'Content-Type' => 'application/json',
                    'stream-auth-type' => 'jwt',
                ],
                'json' => [
                    'users' => [
                        $userId => ['id' => $userId, 'role' => 'user'],
                    ],
                ],
            ]
        );
    }

    /** @param array<string, mixed> $payload */
    private function signJwt(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$body}", $this->apiSecret, true)
        );

        return "{$header}.{$body}.{$signature}";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
