<?php

namespace App\Tests\Entity;

use App\Entity\Inscripcion;
use App\Entity\Actividad;
use App\Entity\Voluntario;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para la entidad Inscripcion
 * NOTA: Esta entidad usa clave compuesta (voluntario + actividad), no tiene getId()
 */
class InscripcionTest extends TestCase
{
    private Inscripcion $inscripcion;

    protected function setUp(): void
    {
        $this->inscripcion = new Inscripcion();
    }

    // ========================================================================
    // TESTS DE PROPIEDADES BÁSICAS
    // ========================================================================

    public function testSetGetEstadoSolicitud(): void
    {
        $estado = 'Aceptado';
        $this->inscripcion->setEstadoSolicitud($estado);
        $this->assertEquals($estado, $this->inscripcion->getEstadoSolicitud());
    }

    public function testEstadoSolicitudPendientePorDefecto(): void
    {
        // Verificar que el estado por defecto es "Pendiente"
        $this->assertEquals('Pendiente', $this->inscripcion->getEstadoSolicitud());
    }

    // ========================================================================
    // TESTS DE ESTADOS VÁLIDOS
    // ========================================================================

    public function testEstadoAceptado(): void
    {
        $this->inscripcion->setEstadoSolicitud('Aceptado');
        $this->assertEquals('Aceptado', $this->inscripcion->getEstadoSolicitud());
    }

    public function testEstadoRechazado(): void
    {
        $this->inscripcion->setEstadoSolicitud('Rechazado');
        $this->assertEquals('Rechazado', $this->inscripcion->getEstadoSolicitud());
    }

    public function testEstadoPendiente(): void
    {
        $this->inscripcion->setEstadoSolicitud('Pendiente');
        $this->assertEquals('Pendiente', $this->inscripcion->getEstadoSolicitud());
    }

    // ========================================================================
    // TESTS DE RELACIÓN ACTIVIDAD
    // ========================================================================

    public function testActividadInicialmenteNula(): void
    {
        $this->assertNull($this->inscripcion->getActividad());
    }

    public function testSetGetActividad(): void
    {
        $actividad = $this->createMock(Actividad::class);

        $this->inscripcion->setActividad($actividad);
        $this->assertSame($actividad, $this->inscripcion->getActividad());
    }

    public function testActividadPuedeSerNula(): void
    {
        $actividad = $this->createMock(Actividad::class);
        $this->inscripcion->setActividad($actividad);
        $this->inscripcion->setActividad(null);

        $this->assertNull($this->inscripcion->getActividad());
    }

    // ========================================================================
    // TESTS DE RELACIÓN VOLUNTARIO
    // ========================================================================

    public function testVoluntarioInicialmenteNulo(): void
    {
        $this->assertNull($this->inscripcion->getVoluntario());
    }

    public function testSetGetVoluntario(): void
    {
        $voluntario = $this->createMock(Voluntario::class);

        $this->inscripcion->setVoluntario($voluntario);
        $this->assertSame($voluntario, $this->inscripcion->getVoluntario());
    }

    // ========================================================================
    // TESTS DE FECHA SOLICITUD
    // ========================================================================

    public function testFechaSolicitudSeInicializaEnConstructor(): void
    {
        $this->assertInstanceOf(\DateTimeInterface::class, $this->inscripcion->getFechaSolicitud());
    }

    public function testSetGetFechaSolicitud(): void
    {
        $fecha = new \DateTime('2025-06-15');
        $this->inscripcion->setFechaSolicitud($fecha);
        $this->assertEquals($fecha, $this->inscripcion->getFechaSolicitud());
    }

    // ========================================================================
    // TESTS DE TIMESTAMPS
    // ========================================================================

    public function testUpdatedAtInicialmenteNulo(): void
    {
        $this->assertNull($this->inscripcion->getUpdatedAt());
    }

    public function testSetGetUpdatedAt(): void
    {
        $fecha = new \DateTime();
        $this->inscripcion->setUpdatedAt($fecha);
        $this->assertEquals($fecha, $this->inscripcion->getUpdatedAt());
    }

    // ========================================================================
    // TESTS FLUENT INTERFACE
    // ========================================================================

    public function testFluentInterface(): void
    {
        $actividad = $this->createMock(Actividad::class);
        $voluntario = $this->createMock(Voluntario::class);

        $result = $this->inscripcion
            ->setActividad($actividad)
            ->setVoluntario($voluntario)
            ->setEstadoSolicitud('Aceptado')
            ->setFechaSolicitud(new \DateTime());

        $this->assertSame($this->inscripcion, $result);
    }

    // ========================================================================
    // TESTS DE INTEGRIDAD
    // ========================================================================

    public function testInscripcionCompleta(): void
    {
        $actividad = $this->createMock(Actividad::class);
        $voluntario = $this->createMock(Voluntario::class);
        $fecha = new \DateTime('2025-06-01');

        $this->inscripcion
            ->setActividad($actividad)
            ->setVoluntario($voluntario)
            ->setEstadoSolicitud('Aceptado')
            ->setFechaSolicitud($fecha);

        $this->assertSame($actividad, $this->inscripcion->getActividad());
        $this->assertSame($voluntario, $this->inscripcion->getVoluntario());
        $this->assertEquals('Aceptado', $this->inscripcion->getEstadoSolicitud());
        $this->assertEquals($fecha, $this->inscripcion->getFechaSolicitud());
    }
}
