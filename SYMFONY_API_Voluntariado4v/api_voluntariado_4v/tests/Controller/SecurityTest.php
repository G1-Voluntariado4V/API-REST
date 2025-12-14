<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
// Nota: Para estos tests necesitamos un usuario simulado.
// Como usamos Firebase, lo ideal sería 'bypassear' la autenticación en entorno TEST 
// o usar un mock de usuario, pero estos tests validarán al menos la respuesta 401 si no hay token.

class SecurityTest extends WebTestCase
{
    // Intento de crear actividad sin ser nadie
    public function testCrearActividadSinToken(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/api/actividades', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'titulo' => 'Intento Hack',
            'descripcion' => 'No tengo permiso'
        ]));

        // Esperamos que nos eche fuera (401 o 403)
        // Como tu API usa Firebase, si no mandas header, suele ser 401.
        $codigo = $client->getResponse()->getStatusCode();
        $this->assertTrue($codigo == 401 || $codigo == 403, 'Debería denegar acceso (401/403) pero devolvió '.$codigo);
    }

    // Intento de ver datos privados de un voluntario sin ser él
    public function testVerPerfilPrivadoSinToken(): void
    {
        $client = static::createClient();
        
        // Asumimos ID 1
        $client->request('GET', '/api/voluntarios/1');

        $codigo = $client->getResponse()->getStatusCode();
        $this->assertTrue($codigo == 401 || $codigo == 403, 'Debería proteger datos personales');
    }
}
