<?php

namespace App\Tests\Controller;

use App\Entity\User;
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
        // L'inscription ne connecte plus : aucun token n'est délivré avant vérification OTP
        $this->assertArrayNotHasKey('token', $response);
        $this->assertArrayNotHasKey('jwtToken', $response);

        // Le compte est créé non vérifié (la vérification se fait via l'OTP)
        $user = static::getContainer()->get('doctrine')->getManager()
            ->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user);
        $this->assertFalse($user->isEmailVerified());
    }

    public function testRegisterThenVerifyOtpReturnsToken(): void
    {
        $client = static::createClient();
        $email = 'otp_' . time() . '@example.com';

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'Test123!@',
            'firstName' => 'Otp',
            'lastName' => 'User'
        ]));
        $this->assertResponseStatusCodeSame(201);

        // Le mailer est null en test : on lit l'OTP directement en base.
        $user = static::getContainer()->get('doctrine')->getManager()
            ->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user);
        $otp = $user->getPhoneOtp();
        $this->assertNotNull($otp);
        $this->assertSame(4, strlen($otp));

        $client->request('POST', '/api/auth/verify-otp', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'identifier' => $email,
            'otp' => $otp,
        ]));
        $this->assertResponseIsSuccessful();
        $res = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($res['verified']);
        $this->assertArrayHasKey('token', $res);
        $this->assertNotEmpty($res['token']);
        $this->assertArrayHasKey('refreshToken', $res);
        $this->assertNotEmpty($res['refreshToken']);
    }

    public function testLoginBlockedBeforeVerification(): void
    {
        $client = static::createClient();
        $email = 'unverified_' . time() . '@example.com';
        $password = 'Test123!@';

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => $password,
            'firstName' => 'Unverified',
            'lastName' => 'User'
        ]));
        $this->assertResponseStatusCodeSame(201);

        // Tant que l'OTP n'est pas vérifié, le login doit être refusé (UserChecker)
        $client->request('POST', '/api/login_check', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => $password,
        ]));
        $this->assertResponseStatusCodeSame(401);
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

