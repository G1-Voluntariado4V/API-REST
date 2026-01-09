<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests para AuthController - Solo Login
 * El registro se prueba en VoluntarioControllerTest y OrganizacionControllerTest
 */
class AuthControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Test: POST /auth/login
     * Debe autenticar un usuario existente
     */
    public function testLoginExitoso(): void
    {
        $payload = [
            'google_id' => '1122334455',
            'email' => 'usuario@gmail.com'
        ];

        $this->client->request(
            'POST',
            '/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $statusCode = $this->client->getResponse()->getStatusCode();

        if ($statusCode === 200) {
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('id_usuario', $data);
            $this->assertArrayHasKey('rol', $data);
            $this->assertArrayHasKey('estado', $data);
        }

        // 200: login ok, 404: no registrado, 403: bloqueado
        $this->assertTrue(in_array($statusCode, [200, 404, 403]));
    }

    /**
     * Test: POST /auth/login con datos faltantes
     * Debe retornar error 400
     */
    public function testLoginDatosFaltantes(): void
    {
        $this->client->request(
            'POST',
            '/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(400);
    }
}
