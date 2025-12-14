<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RolControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testListarRoles(): void
    {
        $this->client->request('GET', '/api/roles');
        
        // Debería ser 200 OK si es público o si hay roles
        // Si no hay roles, devuelve array vacío pero 200 OK.
        $this->assertResponseIsSuccessful();
    }

    public function testCrearRol(): void
    {
        $random = rand(1000, 9999);
        $payload = ['nombre' => 'RolTest_' . $random];

        $this->client->request('POST', '/api/roles', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        
        // Esperamos 201 Created
        $this->assertResponseStatusCodeSame(201);
    }
}
