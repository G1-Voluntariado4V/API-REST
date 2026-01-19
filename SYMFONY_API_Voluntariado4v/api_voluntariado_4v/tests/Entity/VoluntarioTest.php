<?php

namespace App\Tests\Entity;

use App\Entity\Voluntario;
use App\Entity\Usuario;
use App\Entity\Curso;
use App\Entity\Inscripcion;
use App\Entity\TipoVoluntariado;
use App\Entity\VoluntarioIdioma;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para la entidad Voluntario
 */
class VoluntarioTest extends TestCase
{
    private Voluntario $voluntario;

    protected function setUp(): void
    {
        $this->voluntario = new Voluntario();
    }

    // ========================================================================
    // TESTS DE PROPIEDADES BÁSICAS
    // ========================================================================

    public function testIdInicialmenteNulo(): void
    {
        $this->assertNull($this->voluntario->getId());
    }

    public function testSetGetDni(): void
    {
        $dni = '12345678A';
        $this->voluntario->setDni($dni);
        $this->assertEquals($dni, $this->voluntario->getDni());
    }

    public function testDniPuedeSerNulo(): void
    {
        $this->voluntario->setDni(null);
        $this->assertNull($this->voluntario->getDni());
    }

    public function testSetGetNombre(): void
    {
        $nombre = 'Juan';
        $this->voluntario->setNombre($nombre);
        $this->assertEquals($nombre, $this->voluntario->getNombre());
    }

    public function testSetGetApellidos(): void
    {
        $apellidos = 'García López';
        $this->voluntario->setApellidos($apellidos);
        $this->assertEquals($apellidos, $this->voluntario->getApellidos());
    }

    public function testSetGetTelefono(): void
    {
        $telefono = '612345678';
        $this->voluntario->setTelefono($telefono);
        $this->assertEquals($telefono, $this->voluntario->getTelefono());
    }

    public function testTelefonoPuedeSerNulo(): void
    {
        $this->voluntario->setTelefono(null);
        $this->assertNull($this->voluntario->getTelefono());
    }

    public function testSetGetDescripcion(): void
    {
        $descripcion = 'Soy un voluntario apasionado';
        $this->voluntario->setDescripcion($descripcion);
        $this->assertEquals($descripcion, $this->voluntario->getDescripcion());
    }

    public function testDescripcionPuedeSerNula(): void
    {
        $this->voluntario->setDescripcion(null);
        $this->assertNull($this->voluntario->getDescripcion());
    }

    // ========================================================================
    // TESTS DE FECHA DE NACIMIENTO
    // ========================================================================

    public function testSetGetFechaNac(): void
    {
        $fecha = new \DateTime('1990-05-15');
        $this->voluntario->setFechaNac($fecha);
        $this->assertEquals($fecha, $this->voluntario->getFechaNac());
    }

    public function testFechaNacPuedeSerNula(): void
    {
        $this->voluntario->setFechaNac(null);
        $this->assertNull($this->voluntario->getFechaNac());
    }

    // ========================================================================
    // TESTS DE CARNET DE CONDUCIR
    // ========================================================================

    public function testCarnetConducirPorDefecto(): void
    {
        $this->assertFalse($this->voluntario->isCarnetConducir());
    }

    public function testSetCarnetConducirTrue(): void
    {
        $this->voluntario->setCarnetConducir(true);
        $this->assertTrue($this->voluntario->isCarnetConducir());
    }

    public function testSetCarnetConducirFalse(): void
    {
        $this->voluntario->setCarnetConducir(false);
        $this->assertFalse($this->voluntario->isCarnetConducir());
    }

    // ========================================================================
    // TESTS DE RELACIÓN USUARIO
    // ========================================================================

    public function testUsuarioInicialmenteNulo(): void
    {
        $this->assertNull($this->voluntario->getUsuario());
    }

    public function testSetGetUsuario(): void
    {
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('getId')->willReturn(1);

        $this->voluntario->setUsuario($usuario);
        $this->assertSame($usuario, $this->voluntario->getUsuario());
    }

    // ========================================================================
    // TESTS DE RELACIÓN CURSO
    // ========================================================================

    public function testCursoInicialmenteNulo(): void
    {
        $this->assertNull($this->voluntario->getCursoActual());
    }

    public function testSetGetCurso(): void
    {
        $curso = $this->createMock(Curso::class);

        $this->voluntario->setCursoActual($curso);
        $this->assertSame($curso, $this->voluntario->getCursoActual());
    }

    // ========================================================================
    // TESTS DE COLECCIÓN INSCRIPCIONES
    // ========================================================================

    public function testInscripcionesInicialmenteVacias(): void
    {
        $inscripciones = $this->voluntario->getInscripciones();
        $this->assertCount(0, $inscripciones);
    }

    public function testAddInscripcion(): void
    {
        $inscripcion = $this->createMock(Inscripcion::class);

        $this->voluntario->addInscripcion($inscripcion);
        $this->assertTrue($this->voluntario->getInscripciones()->contains($inscripcion));
    }

    public function testRemoveInscripcion(): void
    {
        $inscripcion = $this->createMock(Inscripcion::class);
        $inscripcion->method('getVoluntario')->willReturn($this->voluntario);

        $this->voluntario->addInscripcion($inscripcion);
        $this->voluntario->removeInscripcion($inscripcion);

        $this->assertFalse($this->voluntario->getInscripciones()->contains($inscripcion));
    }

    public function testNoDuplicaInscripciones(): void
    {
        $inscripcion = $this->createMock(Inscripcion::class);

        $this->voluntario->addInscripcion($inscripcion);
        $this->voluntario->addInscripcion($inscripcion);

        $this->assertCount(1, $this->voluntario->getInscripciones());
    }

    // ========================================================================
    // TESTS DE COLECCIÓN PREFERENCIAS
    // ========================================================================

    public function testPreferenciasInicialmenteVacias(): void
    {
        $preferencias = $this->voluntario->getPreferencias();
        $this->assertCount(0, $preferencias);
    }

    public function testAddPreferencia(): void
    {
        $tipo = $this->createMock(TipoVoluntariado::class);

        $this->voluntario->addPreferencia($tipo);
        $this->assertTrue($this->voluntario->getPreferencias()->contains($tipo));
    }

    public function testRemovePreferencia(): void
    {
        $tipo = $this->createMock(TipoVoluntariado::class);

        $this->voluntario->addPreferencia($tipo);
        $this->voluntario->removePreferencia($tipo);

        $this->assertFalse($this->voluntario->getPreferencias()->contains($tipo));
    }

    // ========================================================================
    // TESTS DE COLECCIÓN IDIOMAS
    // ========================================================================

    public function testIdiomasInicialmenteVacios(): void
    {
        $idiomas = $this->voluntario->getVoluntarioIdiomas();
        $this->assertCount(0, $idiomas);
    }

    public function testAddVoluntarioIdioma(): void
    {
        $vi = $this->createMock(VoluntarioIdioma::class);

        $this->voluntario->addVoluntarioIdioma($vi);
        $this->assertTrue($this->voluntario->getVoluntarioIdiomas()->contains($vi));
    }

    public function testRemoveVoluntarioIdioma(): void
    {
        $vi = $this->createMock(VoluntarioIdioma::class);
        $vi->method('getVoluntario')->willReturn($this->voluntario);

        $this->voluntario->addVoluntarioIdioma($vi);
        $this->voluntario->removeVoluntarioIdioma($vi);

        $this->assertFalse($this->voluntario->getVoluntarioIdiomas()->contains($vi));
    }

    // ========================================================================
    // TESTS DE TIMESTAMPS
    // ========================================================================

    public function testUpdatedAtInicialmenteNulo(): void
    {
        $this->assertNull($this->voluntario->getUpdatedAt());
    }

    public function testSetGetUpdatedAt(): void
    {
        $fecha = new \DateTime();
        $this->voluntario->setUpdatedAt($fecha);
        $this->assertEquals($fecha, $this->voluntario->getUpdatedAt());
    }

    // ========================================================================
    // TESTS DE TO_STRING
    // ========================================================================

    public function testToStringSinUsuarioDevuelveMensajePorDefecto(): void
    {
        // Sin usuario, debería devolver 'Voluntario sin usuario'
        $this->assertEquals('Voluntario sin usuario', (string) $this->voluntario);
    }

    public function testToStringConUsuarioDevuelveCorreo(): void
    {
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('getCorreo')->willReturn('test@example.com');

        $this->voluntario->setUsuario($usuario);

        $this->assertEquals('test@example.com', (string) $this->voluntario);
    }

    // ========================================================================
    // TESTS FLUENT INTERFACE
    // ========================================================================

    public function testFluentInterface(): void
    {
        $result = $this->voluntario
            ->setNombre('Test')
            ->setApellidos('Test')
            ->setTelefono('123456789')
            ->setDescripcion('Descripción');

        $this->assertSame($this->voluntario, $result);
    }
}
