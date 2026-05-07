<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CheckInControllerTest extends WebTestCase
{
    public function testCheckInSuccess(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login_check', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'user1@idioma-club.com',
            'password' => 'User123!@'
        ]));

        $response = json_decode($client->getResponse()->getContent(), true);
        $token = $response['token'] ?? '';

        $client->request('POST', '/api/check-in', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'location' => 'Salle 1'
        ]));

        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('checkIn', $response);
    }

    public function testCheckInWithoutToken(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/check-in', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'location' => 'Salle 1'
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCheckInHistoryEndpoint(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login_check', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'user1@idioma-club.com',
            'password' => 'User123!@'
        ]));

        $response = json_decode($client->getResponse()->getContent(), true);
        $token = $response['token'] ?? '';

        $client->request('GET', '/api/check-in/history', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json'
        ]);

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
    }
}

