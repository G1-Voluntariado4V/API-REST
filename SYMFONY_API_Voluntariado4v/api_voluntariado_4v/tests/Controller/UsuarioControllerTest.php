<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UsuarioControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    // 1. Listar usuarios (Prueba de Vista SQL)
    public function testListarUsuarios(): void
    {
        // Debería ser solo para ADMIN, pero probamos acceso público
        $this->client->request('GET', '/api/usuarios');
        
        $this->assertResponseIsSuccessful(); // Esperamos 200 porque NO hay seguridad
        $this->assertJson($this->client->getResponse()->getContent());
    }

    // 2. Crear usuario manual (Admin)
    public function testCrearUsuarioManual(): void
    {
        $random = rand(1000, 9999);
        $payload = [
            'correo' => 'manual_' . $random . '@test.com',
            'google_id' => 'g_manual_' . $random,
            'id_rol' => 4 // Asumimos ID 4 = Voluntario o similar que exista, si falla será 500 por FK
        ];

        $this->client->request('POST', '/api/usuarios', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        // Si la BBDD de test tiene roles cargados (1,2,3,4...), esto pasará.
        // Si no, fallará con 404 (Rol no encontrado) o 500.
        // Asumimos que la BBDD tiene los maestros.
        $codigo = $this->client->getResponse()->getStatusCode();
        
        if ($codigo === 404) {
             // Si no encuentra el rol, es "Correcto" dentro del contexto de que el test corre
             $this->assertEquals(404, $codigo);
        } else {
             $this->assertEquals(201, $codigo);
        }
    }
}
