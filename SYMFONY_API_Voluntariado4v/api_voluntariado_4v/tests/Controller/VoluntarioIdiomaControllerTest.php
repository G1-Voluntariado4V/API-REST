<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VoluntarioIdiomaControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testAsignarIdiomaFaltanDatos(): void
    {
        // Ruta: POST /api/voluntarios/{id}/idiomas
        $this->client->request('POST', '/api/voluntarios/1/idiomas', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'id_idioma' => 1
            // Falta 'nivel'
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAsignarIdiomaVoluntarioNoExiste(): void
    {
        // Probamos con un ID absurdo para recibir un 404 controlado
        $this->client->request('POST', '/api/voluntarios/999999/idiomas', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'id_idioma' => 1,
            'nivel' => 'B2'
        ]));

        $this->assertResponseStatusCodeSame(404);
    }
}
