<?php

namespace App\Tests\Entity;

use App\Entity\Actividad;
use App\Entity\Organizacion;
use App\Entity\ODS;
use App\Entity\TipoVoluntariado;
use App\Entity\Inscripcion;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para la entidad Actividad
 */
class ActividadTest extends TestCase
{
    private Actividad $actividad;

    protected function setUp(): void
    {
        $this->actividad = new Actividad();
    }

    // ========================================================================
    // TESTS DE PROPIEDADES BÁSICAS
    // ========================================================================

    public function testIdInicialmenteNulo(): void
    {
        $this->assertNull($this->actividad->getId());
    }

    public function testSetGetTitulo(): void
    {
        $titulo = 'Limpieza de playas';
        $this->actividad->setTitulo($titulo);
        $this->assertEquals($titulo, $this->actividad->getTitulo());
    }

    public function testSetGetDescripcion(): void
    {
        $descripcion = 'Actividad de voluntariado ambiental';
        $this->actividad->setDescripcion($descripcion);
        $this->assertEquals($descripcion, $this->actividad->getDescripcion());
    }

    public function testDescripcionPuedeSerNula(): void
    {
        $this->actividad->setDescripcion(null);
        $this->assertNull($this->actividad->getDescripcion());
    }

    public function testSetGetFechaInicio(): void
    {
        $fecha = new \DateTime('2025-06-15 10:00:00');
        $this->actividad->setFechaInicio($fecha);
        $this->assertEquals($fecha, $this->actividad->getFechaInicio());
    }

    public function testSetGetDuracionHoras(): void
    {
        $duracion = 4;
        $this->actividad->setDuracionHoras($duracion);
        $this->assertEquals($duracion, $this->actividad->getDuracionHoras());
    }

    public function testSetGetCupoMaximo(): void
    {
        $cupo = 20;
        $this->actividad->setCupoMaximo($cupo);
        $this->assertEquals($cupo, $this->actividad->getCupoMaximo());
    }

    public function testSetGetUbicacion(): void
    {
        $ubicacion = 'Playa de la Malvarrosa, Valencia';
        $this->actividad->setUbicacion($ubicacion);
        $this->assertEquals($ubicacion, $this->actividad->getUbicacion());
    }

    public function testUbicacionPuedeSerNula(): void
    {
        $this->actividad->setUbicacion(null);
        $this->assertNull($this->actividad->getUbicacion());
    }

    // ========================================================================
    // TESTS DE ESTADO DE PUBLICACIÓN
    // ========================================================================

    public function testEstadoPublicacionDefectoEnRevision(): void
    {
        $this->assertEquals('En revision', $this->actividad->getEstadoPublicacion());
    }

    public function testSetEstadoPublicacionPublicada(): void
    {
        $this->actividad->setEstadoPublicacion('Publicada');
        $this->assertEquals('Publicada', $this->actividad->getEstadoPublicacion());
    }

    public function testSetEstadoPublicacionRechazada(): void
    {
        $this->actividad->setEstadoPublicacion('Rechazada');
        $this->assertEquals('Rechazada', $this->actividad->getEstadoPublicacion());
    }

    public function testSetEstadoPublicacionEnCurso(): void
    {
        $this->actividad->setEstadoPublicacion('En curso');
        $this->assertEquals('En curso', $this->actividad->getEstadoPublicacion());
    }

    public function testSetEstadoPublicacionFinalizada(): void
    {
        $this->actividad->setEstadoPublicacion('Finalizada');
        $this->assertEquals('Finalizada', $this->actividad->getEstadoPublicacion());
    }

    // ========================================================================
    // TESTS DE RELACIÓN ORGANIZACIÓN
    // ========================================================================

    public function testOrganizacionInicialmenteNula(): void
    {
        $this->assertNull($this->actividad->getOrganizacion());
    }

    public function testSetGetOrganizacion(): void
    {
        $organizacion = $this->createMock(Organizacion::class);

        $this->actividad->setOrganizacion($organizacion);
        $this->assertSame($organizacion, $this->actividad->getOrganizacion());
    }

    public function testOrganizacionPuedeSerNula(): void
    {
        $organizacion = $this->createMock(Organizacion::class);
        $this->actividad->setOrganizacion($organizacion);
        $this->actividad->setOrganizacion(null);

        $this->assertNull($this->actividad->getOrganizacion());
    }

    // ========================================================================
    // TESTS DE COLECCIÓN ODS
    // ========================================================================

    public function testOdsInicialmenteVacios(): void
    {
        $ods = $this->actividad->getOds();
        $this->assertCount(0, $ods);
    }

    public function testAddOds(): void
    {
        $ods = $this->createMock(ODS::class);

        $this->actividad->addOd($ods);
        $this->assertTrue($this->actividad->getOds()->contains($ods));
    }

    public function testRemoveOds(): void
    {
        $ods = $this->createMock(ODS::class);

        $this->actividad->addOd($ods);
        $this->actividad->removeOd($ods);

        $this->assertFalse($this->actividad->getOds()->contains($ods));
    }

    public function testNoDuplicaOds(): void
    {
        $ods = $this->createMock(ODS::class);

        $this->actividad->addOd($ods);
        $this->actividad->addOd($ods);

        $this->assertCount(1, $this->actividad->getOds());
    }

    // ========================================================================
    // TESTS DE COLECCIÓN TIPOS VOLUNTARIADO
    // ========================================================================

    public function testTiposInicialmenteVacios(): void
    {
        $tipos = $this->actividad->getTiposVoluntariado();
        $this->assertCount(0, $tipos);
    }

    public function testAddTipoVoluntariado(): void
    {
        $tipo = $this->createMock(TipoVoluntariado::class);

        $this->actividad->addTiposVoluntariado($tipo);
        $this->assertTrue($this->actividad->getTiposVoluntariado()->contains($tipo));
    }

    public function testRemoveTipoVoluntariado(): void
    {
        $tipo = $this->createMock(TipoVoluntariado::class);

        $this->actividad->addTiposVoluntariado($tipo);
        $this->actividad->removeTiposVoluntariado($tipo);

        $this->assertFalse($this->actividad->getTiposVoluntariado()->contains($tipo));
    }

    // ========================================================================
    // TESTS DE COLECCIÓN INSCRIPCIONES
    // ========================================================================

    public function testInscripcionesInicialmenteVacias(): void
    {
        $inscripciones = $this->actividad->getInscripciones();
        $this->assertCount(0, $inscripciones);
    }

    public function testAddInscripcion(): void
    {
        $inscripcion = $this->createMock(Inscripcion::class);
        // El método espera poder llamar a setActividad
        $inscripcion->expects($this->once())
            ->method('setActividad')
            ->with($this->actividad);

        $this->actividad->addInscripcion($inscripcion);
        $this->assertTrue($this->actividad->getInscripciones()->contains($inscripcion));
    }

    public function testRemoveInscripcion(): void
    {
        $inscripcion = $this->createMock(Inscripcion::class);
        $inscripcion->method('getActividad')->willReturn($this->actividad);
        $inscripcion->method('setActividad');

        $this->actividad->addInscripcion($inscripcion);
        $this->actividad->removeInscripcion($inscripcion);

        $this->assertFalse($this->actividad->getInscripciones()->contains($inscripcion));
    }

    // ========================================================================
    // TESTS DE TIMESTAMPS Y SOFT DELETE
    // ========================================================================

    public function testUpdatedAtInicialmenteNulo(): void
    {
        $this->assertNull($this->actividad->getUpdatedAt());
    }

    public function testSetGetUpdatedAt(): void
    {
        $fecha = new \DateTime();
        $this->actividad->setUpdatedAt($fecha);
        $this->assertEquals($fecha, $this->actividad->getUpdatedAt());
    }

    public function testDeletedAtInicialmenteNulo(): void
    {
        $this->assertNull($this->actividad->getDeletedAt());
    }

    public function testSetGetDeletedAt(): void
    {
        $fecha = new \DateTimeImmutable();
        $this->actividad->setDeletedAt($fecha);
        $this->assertEquals($fecha, $this->actividad->getDeletedAt());
    }

    public function testActividadNoEliminadaCuandoDeletedAtEsNulo(): void
    {
        $this->assertNull($this->actividad->getDeletedAt());
    }

    public function testActividadSoftDeleteCuandoDeletedAtTieneValor(): void
    {
        $fecha = new \DateTimeImmutable();
        $this->actividad->setDeletedAt($fecha);

        $this->assertNotNull($this->actividad->getDeletedAt());
    }

    // ========================================================================
    // TESTS FLUENT INTERFACE
    // ========================================================================

    public function testFluentInterface(): void
    {
        $result = $this->actividad
            ->setTitulo('Test')
            ->setDescripcion('Descripción')
            ->setDuracionHoras(4)
            ->setCupoMaximo(20)
            ->setUbicacion('Test Location')
            ->setEstadoPublicacion('Publicada');

        $this->assertSame($this->actividad, $result);
    }

    // ========================================================================
    // TESTS DE INTEGRIDAD
    // ========================================================================

    public function testActividadCompletaConTodosLosDatos(): void
    {
        $organizacion = $this->createMock(Organizacion::class);
        $ods = $this->createMock(ODS::class);
        $tipo = $this->createMock(TipoVoluntariado::class);

        $this->actividad
            ->setTitulo('Actividad Completa')
            ->setDescripcion('Descripción completa')
            ->setFechaInicio(new \DateTime('2025-07-01'))
            ->setDuracionHoras(6)
            ->setCupoMaximo(15)
            ->setUbicacion('Centro de Valencia')
            ->setEstadoPublicacion('Publicada')
            ->setOrganizacion($organizacion)
            ->addOd($ods)
            ->addTiposVoluntariado($tipo);

        $this->assertEquals('Actividad Completa', $this->actividad->getTitulo());
        $this->assertEquals(6, $this->actividad->getDuracionHoras());
        $this->assertEquals(15, $this->actividad->getCupoMaximo());
        $this->assertSame($organizacion, $this->actividad->getOrganizacion());
        $this->assertCount(1, $this->actividad->getOds());
        $this->assertCount(1, $this->actividad->getTiposVoluntariado());
    }
}
