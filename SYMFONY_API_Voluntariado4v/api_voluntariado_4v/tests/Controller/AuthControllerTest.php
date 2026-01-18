<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests funcionales para AuthController
 */
class AuthControllerTest extends WebTestCase
{
    // ========================================================================
    // TESTS DE LOGIN
    // ========================================================================

    public function testLoginSinDatosDevuelveBadRequest(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('mensaje', $content);
    }

    public function testLoginConGoogleIdInexistenteDevuelve404(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'google_id' => 'usuario_inexistente_12345',
                'email' => 'noexiste@example.com'
            ])
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testLoginEndpointExiste(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['google_id' => 'test'])
        );

        // El endpoint debe existir (404 o 200, no 500)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $statusCode);
    }

    public function testLoginSoloAceptaMetodoPOST(): void
    {
        $client = static::createClient();

        // GET debería devolver Method Not Allowed
        $client->request('GET', '/auth/login');
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());
    }

    public function testLoginRespuestaEsJSON(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['google_id' => 'test'])
        );

        $this->assertJson($client->getResponse()->getContent());
    }
}
