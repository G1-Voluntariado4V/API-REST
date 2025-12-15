<?php

namespace App\Tests\Entity;

use App\Entity\Coordinador;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;

class CoordinadorTest extends TestCase
{
    public function testDatosPropios(): void
    {
        $coordinador = new Coordinador();
        $coordinador->setNombre('Juan');
        $coordinador->setApellidos('Pérez');
        $coordinador->setTelefono('666777888');
        
        $this->assertEquals('Juan', $coordinador->getNombre());
        $this->assertEquals('Pérez', $coordinador->getApellidos());
        $this->assertEquals('666777888', $coordinador->getTelefono());
    }

    public function testRelacionConUsuario(): void
    {
        $usuario = new Usuario();
        // Simulamos ID si tuviera setter, o confiamos en la vinculación de objeto
        
        $coordinador = new Coordinador();
        $coordinador->setUsuario($usuario);

        $this->assertSame($usuario, $coordinador->getUsuario());
    }
}
