<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ActividadControllerTest extends WebTestCase
{
    // Este test verifica que cualquiera (incluso sin login) puede ver la lista de actividades
    public function testListarActividadesPublicas(): void
    {
        $client = static::createClient();
        
        // Hacemos la petición real al endpoint correct (prefijo /api)
        $client->request('GET', '/api/actividades');

        // VALIDACIÓN 1: El servidor responde OK (200)
        $this->assertResponseIsSuccessful();
        
        // VALIDACIÓN 2: La respuesta es JSON
        $this->assertResponseHeaderSame('content-type', 'application/json');

        // VALIDACIÓN 3: Estructura del contenido
        $content = $client->getResponse()->getContent();
        $this->assertJson($content);
        
        // Convertimos a array para inspeccionar
        $data = json_decode($content, true);
        
        // Debe ser una lista (array)
        $this->assertIsArray($data);
        
        // Si hay datos, verificamos que tengan campos clave
        if (count($data) > 0) {
            $this->assertArrayHasKey('titulo', $data[0], 'La actividad debería tener título');
            $this->assertArrayHasKey('id_actividad', $data[0], 'La actividad debería tener ID');
        }
    }

    // Este test verifica que si pides algo que no existe, la API no explote, sino que diga 404
    public function testDetalleActividadInexistente(): void
    {
        $client = static::createClient();
        
        // Pedimos un ID imposible
        $client->request('GET', '/api/actividades/999999');

        // VALIDACIÓN: Debe ser un error 404 Not Found
        $this->assertResponseStatusCodeSame(404);
    }
}
