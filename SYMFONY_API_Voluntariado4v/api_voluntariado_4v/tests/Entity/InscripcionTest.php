<?php

namespace App\Tests\Entity;

use App\Entity\Inscripcion;
use App\Entity\Voluntario;
use App\Entity\Actividad;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para la entidad Inscripcion
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

    public function testInscripcionInstanciacionCorrecta(): void
    {
        $this->assertInstanceOf(Inscripcion::class, $this->inscripcion);
        $this->assertInstanceOf(\DateTimeInterface::class, $this->inscripcion->getFechaSolicitud());
    }

    public function testEstadoSolicitudPorDefecto(): void
    {
        $this->assertEquals('Pendiente', $this->inscripcion->getEstadoSolicitud());
    }

    public function testSetYGetEstadoSolicitud(): void
    {
        $this->inscripcion->setEstadoSolicitud('Aceptada');
        $this->assertEquals('Aceptada', $this->inscripcion->getEstadoSolicitud());

        $this->inscripcion->setEstadoSolicitud('Rechazada');
        $this->assertEquals('Rechazada', $this->inscripcion->getEstadoSolicitud());

        $this->inscripcion->setEstadoSolicitud('Confirmada');
        $this->assertEquals('Confirmada', $this->inscripcion->getEstadoSolicitud());
    }

    // ========================================================================
    // TESTS DE FECHA SOLICITUD
    // ========================================================================

    public function testFechaSolicitudAutomatica(): void
    {
        $fechaAntes = new \DateTime();
        $inscripcion = new Inscripcion();
        $fechaDespues = new \DateTime();

        $this->assertGreaterThanOrEqual($fechaAntes->getTimestamp(), $inscripcion->getFechaSolicitud()->getTimestamp());
        $this->assertLessThanOrEqual($fechaDespues->getTimestamp(), $inscripcion->getFechaSolicitud()->getTimestamp());
    }

    public function testSetYGetFechaSolicitud(): void
    {
        $fecha = new \DateTime('2024-06-01 10:00:00');
        $this->inscripcion->setFechaSolicitud($fecha);
        $this->assertEquals($fecha, $this->inscripcion->getFechaSolicitud());
    }

    // ========================================================================
    // TESTS DE RELACIÓN VOLUNTARIO
    // ========================================================================

    public function testVoluntarioInicialmenteNulo(): void
    {
        $this->assertNull($this->inscripcion->getVoluntario());
    }

    public function testSetYGetVoluntario(): void
    {
        $voluntario = $this->createMock(Voluntario::class);

        $this->inscripcion->setVoluntario($voluntario);
        $this->assertSame($voluntario, $this->inscripcion->getVoluntario());
    }

    public function testVoluntarioPuedeSerNulo(): void
    {
        $voluntario = $this->createMock(Voluntario::class);
        $this->inscripcion->setVoluntario($voluntario);
        $this->inscripcion->setVoluntario(null);

        $this->assertNull($this->inscripcion->getVoluntario());
    }

    // ========================================================================
    // TESTS DE RELACIÓN ACTIVIDAD
    // ========================================================================

    public function testActividadInicialmenteNula(): void
    {
        $this->assertNull($this->inscripcion->getActividad());
    }

    public function testSetYGetActividad(): void
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
    // TESTS DE TIMESTAMPS
    // ========================================================================

    public function testUpdatedAtInicialmenteNulo(): void
    {
        $this->assertNull($this->inscripcion->getUpdatedAt());
    }

    public function testSetYGetUpdatedAt(): void
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
        $voluntario = $this->createMock(Voluntario::class);
        $actividad = $this->createMock(Actividad::class);

        $result = $this->inscripcion
            ->setVoluntario($voluntario)
            ->setActividad($actividad)
            ->setEstadoSolicitud('Confirmada');

        $this->assertSame($this->inscripcion, $result);
    }

    // ========================================================================
    // TESTS DE ESTADOS VÁLIDOS
    // ========================================================================

    public function testEstadosSolicitudValidos(): void
    {
        $estadosValidos = ['Pendiente', 'Aceptada', 'Rechazada', 'Confirmada', 'Finalizada'];

        foreach ($estadosValidos as $estado) {
            $this->inscripcion->setEstadoSolicitud($estado);
            $this->assertEquals($estado, $this->inscripcion->getEstadoSolicitud());
        }
    }
}
