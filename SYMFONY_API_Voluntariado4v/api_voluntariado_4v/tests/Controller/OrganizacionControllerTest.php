<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrganizacionControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    // 1. Registro Exitoso
    public function testRegistrarOrganizacionExito(): void
    {
        // Generamos datos aleatorios para evitar colisiones en tests repetidos
        $random = rand(1000, 9999);
        $cif = 'B1234' . $random;
        $email = 'ong_test_' . $random . '@example.com';
        $googleId = 'google_ong_' . $random;

        $payload = [
            'google_id' => $googleId,
            'correo' => $email,
            'nombre' => 'ONG Tester Global',
            'cif' => $cif,
            'descripcion' => 'Descripción de prueba',
            'direccion' => 'Calle Falsa 123',
            'sitio_web' => 'https://ong-test.org',
            'telefono' => '600123456'
        ];

        $this->client->request('POST', '/api/organizaciones', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        $this->assertResponseStatusCodeSame(201);
        
        // Verificamos que devuelve JSON con el nombre
        $responseContent = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('ONG Tester Global', $responseContent);
    }

    // 2. Fallo por datos faltantes
    public function testRegistrarOrganizacionFaltanDatos(): void
    {
        $payload = [
            'nombre' => 'ONG Incompleta'
            // Falta CIF, Correo, GoogleID
        ];

        $this->client->request('POST', '/api/organizaciones', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        $this->assertResponseStatusCodeSame(400); // Bad Request
    }

    // 3. Fallo por duplicado (ejecutamos dos veces el mismo registro)
    public function testRegistroDuplicado(): void
    {
        // Usamos un dato fijo para asegurar colisión
        $cif = 'B_DUPLICADO_999';
        $email = 'duplicado@ong.com';
        $googleId = 'google_dup_999';

        $payload = [
            'google_id' => $googleId,
            'correo' => $email,
            'nombre' => 'ONG Duplicada',
            'cif' => $cif
        ];

        // Primer intento: Debe ser 201 (o 409 si ya existe de ejecuciones anteriores)
        $this->client->request('POST', '/api/organizaciones', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        
        // No asertamos aquí porque depende del estado previo de la BBDD, 
        // pero el segundo intento SÍ debe fallar obligatoriamente.

        // Segundo intento: Debe ser 409 Conflict
        $this->client->request('POST', '/api/organizaciones', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        
        $this->assertResponseStatusCodeSame(409);
    }
}
