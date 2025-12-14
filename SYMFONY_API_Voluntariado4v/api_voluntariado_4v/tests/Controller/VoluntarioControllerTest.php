<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VoluntarioControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    // 1. Registro Exitoso
    public function testRegistrarVoluntarioExito(): void
    {
        $random = rand(10000, 99999);
        $email = 'vol_' . $random . '@test.com';
        $googleId = 'g_vol_' . $random;

        $payload = [
            'google_id' => $googleId,
            'correo' => $email,
            'nombre' => 'Juan',
            'apellidos' => 'Pérez Test',
            'dni' => $random . 'X',
            'fecha_nac' => '2000-05-20',
            'telefono' => '666777888',
            // Opcionales que podríamos probar
            'preferencias_ids' => [1], // Asumiendo que ID 1 existe en TipoVoluntariado
            'idiomas' => [
                ['id_idioma' => 1, 'nivel' => 'C1'] // Asumiendo ID 1 existe
            ]
        ];

        $this->client->request('POST', '/api/voluntarios', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        // Puede fallar si los IDs de Idioma/Tipo no existen en la BBDD de test.
        // Si falla con 500, revisar si existen los catálogos.
        // Asumimos que la BBDD de test tiene datos básicos cargados.
        
        // Si devuelve 201 es éxito.
        // Si devuelve 500 puede ser por las FKs.
        $codigo = $this->client->getResponse()->getStatusCode();

        if ($codigo === 500) {
           // Si falla, imprimimos el error para debug (opcional en desarrollo)
           // fwrite(STDERR, $this->client->getResponse()->getContent());
        }

        $this->assertEquals(201, $codigo);
        $this->assertStringContainsString('Juan', $this->client->getResponse()->getContent());
    }

    // 2. Validación
    public function testRegistrarVoluntarioFaltanDatos(): void
    {
        $payload = [
            'nombre' => 'Solo Nombre'
        ];

        $this->client->request('POST', '/api/voluntarios', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        $this->assertResponseStatusCodeSame(400);
    }
}
