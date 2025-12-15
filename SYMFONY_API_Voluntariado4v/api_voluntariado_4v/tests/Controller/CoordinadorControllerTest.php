<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CoordinadorControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    // 1. Registro de Coordinador (Seguramente falle igual que los otros por el bug de 'nombreRol')
    public function testRegistrarCoordinadorExito(): void
    {
        $random = rand(1000, 9999);
        $email = 'coord_' . $random . '@admin.com';
        $googleId = 'g_coord_' . $random;

        $payload = [
            'google_id' => $googleId,
            'correo' => $email,
            'nombre' => 'Jefe Coordinador'
        ];

        $this->client->request('POST', '/api/coordinadores', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        // Esperamos 201 Created
        // Si falla con 500, confirmará que el bug de 'nombreRol' es sistémico en todos los controladores.
        $this->assertResponseStatusCodeSame(201);
    }

    // 2. Dashboard Global (debería requerir permisos, pero probamos acceso público por ahora)
    public function testDashboardGlobal(): void
    {
        // Se asume que /api/coord/stats está protegido o abierto.
        // Si la seguridad está desactivada, debería devolver 200.
        $this->client->request('GET', '/api/coord/stats');
        
        $statusCode = $this->client->getResponse()->getStatusCode();

        // Validamos que devuelva OK (si es público) o 401 (si tiene algo de seguridad básica)
        // Actualmente esperamos 200 porque sabemos que security.yaml está abierto.
        if ($statusCode === 200) {
            $this->assertResponseIsSuccessful();
            $this->assertJson($this->client->getResponse()->getContent());
        } else {
             $this->assertEquals(401, $statusCode);
        }
    }

    // 3. Moderación de Usuario (Cambiar estado)
    public function testModeracionUsuarioAnonima(): void
    {
        // Intentar bloquear a un usuario siendo anónimo
        $payload = ['estado' => 'Bloqueada'];
        
        // ID 1 se asume existente (fixture básica)
        $this->client->request('PATCH', '/api/coord/voluntarios/1/estado', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        // Debería ser 401, pero será 200 si no hay seguridad.
        // Asertamos 401 para evidenciar la falta de seguridad.
        $this->assertResponseStatusCodeSame(401);
    }
}
