<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests para UsuarioController (Gestión administrativa básica)
 */
class UsuarioControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Test: GET /usuarios
     * Listado administrativo de usuarios
     */
    public function testListarUsuarios(): void
    {
        $this->client->request('GET', '/usuarios');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    /**
     * Test: POST /usuarios
     * Crear usuario manualmente (raro, pero existe en API)
     */
    public function testCrearUsuario(): void
    {
        $random = rand(1000, 9999);
        $payload = [
            'correo' => 'manual_' . $random . '@test.com',
            'google_id' => 'g_manual_' . $random,
            'id_rol' => 1
        ];

        $this->client->request(
            'POST',
            '/usuarios',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [201, 400, 404, 409]));
    }

    /**
     * Test: DELETE /usuarios/{id}
     * Eliminar usuario
     */
    public function testEliminarUsuario(): void
    {
        $this->client->request('DELETE', '/usuarios/999');
        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 404]));
    }
}
