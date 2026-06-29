<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Couvre la ressource check-in consolidée (/api/check_ins) : sécurité,
 * vérification d'abonnement à la création, et check-out.
 */
class CheckInResourceTest extends WebTestCase
{
    private function login(KernelBrowser $client, string $email, string $password): string
    {
        $client->request('POST', '/api/login_check', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => $password,
        ]));
        $this->assertResponseStatusCodeSame(200);

        return json_decode($client->getResponse()->getContent(), true)['token'];
    }

    public function testCreateRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/check_ins', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'location' => 'Salle A',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateRequiresActiveSubscription(): void
    {
        $client = static::createClient();
        // admin n'a pas d'abonnement actif dans les fixtures
        $token = $this->login($client, 'admin@idioma-club.com', 'Admin123!@');

        $client->request('POST', '/api/check_ins', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['location' => 'Salle A']));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateThenCheckoutFlow(): void
    {
        $client = static::createClient();
        // user1 a un abonnement actif dans les fixtures
        $token = $this->login($client, 'user1@idioma-club.com', 'User123!@');
        $auth = ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token];

        // Création
        $client->request('POST', '/api/check_ins', [], [], $auth, json_encode(['location' => 'Salle A']));
        $this->assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($created['id']);
        $this->assertNull($created['checkedOutAt']);

        // Check-out
        $client->request('POST', "/api/check_ins/{$created['id']}/checkout", [], [], $auth);
        $this->assertResponseIsSuccessful();
        $checkedOut = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotNull($checkedOut['checkedOutAt']);
    }

    public function testCollectionIsScopedToCurrentUser(): void
    {
        $client = static::createClient();

        // user1 crée un check-in
        $token1 = $this->login($client, 'user1@idioma-club.com', 'User123!@');
        $client->request('POST', '/api/check_ins', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token1,
        ], json_encode(['location' => 'Salle A']));
        $this->assertResponseStatusCodeSame(201);
        $createdId = json_decode($client->getResponse()->getContent(), true)['id'];

        // user2 ne doit pas voir le check-in de user1
        $token2 = $this->login($client, 'user2@idioma-club.com', 'User123!@');
        $client->request('GET', '/api/check_ins', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token2,
        ]);
        $this->assertResponseIsSuccessful();

        $body = json_decode($client->getResponse()->getContent(), true);
        $members = $body['hydra:member'] ?? $body['member'] ?? [];
        $ids = array_column($members, 'id');
        $this->assertNotContains($createdId, $ids, "user2 ne doit pas voir le check-in de user1");
    }
}
