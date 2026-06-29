<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Couvre le vrai endpoint de mise à jour du profil (PATCH /api/auth/profile),
 * utilisé par le frontend. Remplace l'ancien DashboardControllerTest qui visait
 * des routes /api/dashboard/* aujourd'hui supprimées.
 */
class ProfileControllerTest extends WebTestCase
{
    /**
     * Se connecte avec l'utilisateur admin des fixtures et renvoie le JWT.
     */
    private function loginAsAdmin(KernelBrowser $client): string
    {
        $client->request('POST', '/api/login_check', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'admin@idioma-club.com',
            'password' => 'Admin123!@',
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);

        return $data['token'];
    }

    public function testUpdateProfileRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('PATCH', '/api/auth/profile', ['firstName' => 'Hacker']);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUpdateProfileSucceedsWhenAuthenticated(): void
    {
        $client = static::createClient();
        $token = $this->loginAsAdmin($client);

        $client->request(
            'PATCH',
            '/api/auth/profile',
            ['firstName' => 'AdminUpdated', 'lastName' => 'NameUpdated'],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('AdminUpdated', $data['user']['firstName']);
    }
}
