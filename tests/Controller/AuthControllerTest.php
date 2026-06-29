<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
{
    public function testRegisterSuccess(): void
    {
        $client = static::createClient();

        $email = 'test_' . time() . '@example.com';
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'Test123!@',
            'firstName' => 'Test',
            'lastName' => 'User'
        ]));

        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('email', $response);
        $this->assertEquals($email, $response['email']);
    }

    public function testRegisterDuplicateEmail(): void
    {
        $client = static::createClient();

        // First registration
        $email = 'duplicate_' . time() . '@example.com';
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'Test123!@',
            'firstName' => 'Test',
            'lastName' => 'User'
        ]));
        $this->assertResponseStatusCodeSame(201);

        // Second registration with same email
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'Test123!@',
            'firstName' => 'Test',
            'lastName' => 'User'
        ]));
        // Le processor détecte l'email déjà utilisé et renvoie 409 Conflict
        $this->assertResponseStatusCodeSame(409);
    }

    public function testLoginSuccess(): void
    {
        $client = static::createClient();

        // Use the admin user from fixtures
        $client->request('POST', '/api/login_check', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'admin@idioma-club.com',
            'password' => 'Admin123!@'
        ]));

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $response);
    }

    public function testLoginInvalidCredentials(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/login_check', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'nonexistent@example.com',
            'password' => 'WrongPassword123!@'
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

}

