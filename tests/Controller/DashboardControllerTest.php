<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    public function testProfileEndpoint(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login_check', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'user1@idioma-club.com',
            'password' => 'User123!@'
        ]));

        $response = json_decode($client->getResponse()->getContent(), true);
        $token = $response['token'] ?? '';

        $client->request('GET', '/api/dashboard/profile', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json'
        ]);

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('user', $response);
        $this->assertArrayHasKey('id', $response['user']);
        $this->assertArrayHasKey('email', $response['user']);
    }

    public function testProfileWithoutToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/dashboard/profile', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testQrCodeEndpoint(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login_check', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'user1@idioma-club.com',
            'password' => 'User123!@'
        ]));

        $response = json_decode($client->getResponse()->getContent(), true);
        $token = $response['token'] ?? '';

        $client->request('GET', '/api/dashboard/qr-code', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json'
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('image/png', $client->getResponse()->headers->get('Content-Type'));
    }
}

