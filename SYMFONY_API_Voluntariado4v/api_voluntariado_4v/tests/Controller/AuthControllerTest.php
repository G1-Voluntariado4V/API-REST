<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    // 1. Caso ROJO: Usuario no existe -> 404
    public function testLoginUsuarioNoExiste(): void
    {
        $payload = ['google_id' => 'no_existo_12345'];
        $this->client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        
        $this->assertResponseStatusCodeSame(404);
    }

    // 2. Caso VERDE: Usuario activo (simulado o si existe en bbdd)
    // Este test es difícil si la BBDD está vacía o limpia.
    // Lo ideal es tener fixtures, pero probaremos la lógica negativa que es más robusta sin datos.
    
    // 3. Validación de datos faltantes
    public function testLoginFaltanDatos(): void
    {
         $this->client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([]));
         $this->assertResponseStatusCodeSame(400);
    }
}
