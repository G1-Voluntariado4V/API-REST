<?php

namespace App\Tests\Entity;

use App\Entity\Organizacion;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para la entidad Organizacion
 */
class OrganizacionTest extends TestCase
{
    private Organizacion $organizacion;

    protected function setUp(): void
    {
        $this->organizacion = new Organizacion();
    }

    // ========================================================================
    // TESTS DE PROPIEDADES BÁSICAS
    // ========================================================================

    public function testIdInicialmenteNulo(): void
    {
        $this->assertNull($this->organizacion->getId());
    }

    public function testSetGetCif(): void
    {
        $cif = 'B12345678';
        $this->organizacion->setCif($cif);
        $this->assertEquals($cif, $this->organizacion->getCif());
    }

    public function testCifPuedeSerNulo(): void
    {
        $this->organizacion->setCif(null);
        $this->assertNull($this->organizacion->getCif());
    }

    public function testSetGetNombre(): void
    {
        $nombre = 'ONG Solidaria';
        $this->organizacion->setNombre($nombre);
        $this->assertEquals($nombre, $this->organizacion->getNombre());
    }

    public function testNombrePuedeSerNulo(): void
    {
        $this->organizacion->setNombre(null);
        $this->assertNull($this->organizacion->getNombre());
    }

    public function testSetGetDescripcion(): void
    {
        $descripcion = 'Somos una organización dedicada a ayudar';
        $this->organizacion->setDescripcion($descripcion);
        $this->assertEquals($descripcion, $this->organizacion->getDescripcion());
    }

    public function testDescripcionPuedeSerNula(): void
    {
        $this->organizacion->setDescripcion(null);
        $this->assertNull($this->organizacion->getDescripcion());
    }

    public function testSetGetDireccion(): void
    {
        $direccion = 'Calle Mayor 123, Madrid';
        $this->organizacion->setDireccion($direccion);
        $this->assertEquals($direccion, $this->organizacion->getDireccion());
    }

    public function testDireccionPuedeSerNula(): void
    {
        $this->organizacion->setDireccion(null);
        $this->assertNull($this->organizacion->getDireccion());
    }

    public function testSetGetSitioWeb(): void
    {
        $sitioWeb = 'https://www.ejemplo.org';
        $this->organizacion->setSitioWeb($sitioWeb);
        $this->assertEquals($sitioWeb, $this->organizacion->getSitioWeb());
    }

    public function testSitioWebPuedeSerNulo(): void
    {
        $this->organizacion->setSitioWeb(null);
        $this->assertNull($this->organizacion->getSitioWeb());
    }

    public function testSetGetTelefono(): void
    {
        $telefono = '912345678';
        $this->organizacion->setTelefono($telefono);
        $this->assertEquals($telefono, $this->organizacion->getTelefono());
    }

    public function testTelefonoPuedeSerNulo(): void
    {
        $this->organizacion->setTelefono(null);
        $this->assertNull($this->organizacion->getTelefono());
    }

    // ========================================================================
    // TESTS DE RELACIÓN USUARIO
    // ========================================================================

    public function testUsuarioInicialmenteNulo(): void
    {
        $this->assertNull($this->organizacion->getUsuario());
    }

    public function testSetGetUsuario(): void
    {
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('getId')->willReturn(5);

        $this->organizacion->setUsuario($usuario);
        $this->assertSame($usuario, $this->organizacion->getUsuario());
    }

    public function testSetUsuarioEstableceIdDesdeUsuario(): void
    {
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('getId')->willReturn(10);

        $this->organizacion->setUsuario($usuario);
        $this->assertEquals(10, $this->organizacion->getId());
    }

    // ========================================================================
    // TESTS DE TIMESTAMPS
    // ========================================================================

    public function testUpdatedAtInicialmenteNulo(): void
    {
        $this->assertNull($this->organizacion->getUpdatedAt());
    }

    public function testSetGetUpdatedAt(): void
    {
        $fecha = new \DateTime();
        $this->organizacion->setUpdatedAt($fecha);
        $this->assertEquals($fecha, $this->organizacion->getUpdatedAt());
    }

    public function testUpdatedAtPuedeSerNulo(): void
    {
        $this->organizacion->setUpdatedAt(null);
        $this->assertNull($this->organizacion->getUpdatedAt());
    }

    // ========================================================================
    // TESTS FLUENT INTERFACE
    // ========================================================================

    public function testFluentInterface(): void
    {
        $result = $this->organizacion
            ->setNombre('Test')
            ->setCif('B12345678')
            ->setDescripcion('Descripción')
            ->setDireccion('Calle Test')
            ->setSitioWeb('https://test.com')
            ->setTelefono('123456789');

        $this->assertSame($this->organizacion, $result);
    }

    // ========================================================================
    // TESTS DE VALIDACIÓN DE FORMATO CIF
    // ========================================================================

    public function testCifFormatoValido(): void
    {
        // Este test verifica que se pueden establecer diferentes formatos de CIF
        $cifs = ['B12345678', 'A87654321', 'G00000001'];

        foreach ($cifs as $cif) {
            $this->organizacion->setCif($cif);
            $this->assertEquals($cif, $this->organizacion->getCif());
        }
    }
}
