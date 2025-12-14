<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class InscripcionControllerTest extends WebTestCase
{
    // Este tests valida que si intentas inscribirte sin login, falla.
    // (Para probar el éxito necesitaríamos Mockear el usuario logueado en Symfony, que es un paso avanzado)
    public function testInscripcionAnonimaFalla(): void
    {
        $client = static::createClient();
        
        // URL Correcta según VoluntarioController: /api/voluntarios/{id}/actividades/{idActividad}
        // Usamos IDs ficticios (1 y 1) esperando un 401 (Unauthorized)
        $client->request('POST', '/api/voluntarios/1/actividades/1', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'mensaje' => 'Quiero participar'
        ]));

        // Debe fallar por seguridad
        $this->assertResponseStatusCodeSame(401);
    }

    // Este test verifica que puedes listar inscripciones (si tuvieras permiso)
    // Pero como es público el endpoint de "ver aspirantes" (según rol ONG),    // Test de seguridad: ver aspirantes sin permiso
    public function testVerAspirantesAnonimoFalla(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/actividades/1/inscripciones');
        
        // Seguridad ante todo
        $this->assertResponseStatusCodeSame(401);
    }
}
