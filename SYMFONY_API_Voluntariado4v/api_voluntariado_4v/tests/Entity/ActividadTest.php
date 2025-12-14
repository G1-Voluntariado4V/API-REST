<?php

namespace App\Tests\Entity;

use App\Entity\Actividad;
use App\Entity\Inscripcion;
use App\Entity\Organizacion;
use PHPUnit\Framework\TestCase;

class ActividadTest extends TestCase
{
    public function testValoresIniciales(): void
    {
        $actividad = new Actividad();

        // Verificamos que las colecciones se inicializan
        $this->assertCount(0, $actividad->getOds());
        $this->assertCount(0, $actividad->getTiposVoluntariado());
        $this->assertCount(0, $actividad->getInscripciones());
        
        // Verificamos estado por defecto
        $this->assertEquals('En revision', $actividad->getEstadoPublicacion());
    }

    public function testAddRemoveInscripcionLathicLogic(): void
    {
        // 1. Crear Actividad e Inscripción
        $actividad = new Actividad();
        $inscripcion = new Inscripcion();

        // 2. Añadir Inscripción a la Actividad
        $actividad->addInscripcion($inscripcion);

        // ASERCIÓN 1: La actividad debe tener 1 inscripción
        $this->assertCount(1, $actividad->getInscripciones());
        $this->assertTrue($actividad->getInscripciones()->contains($inscripcion));

        // ASERCIÓN 2 (La más importante): La inscripción debe saber cuál es su actividad (Sincronización Bidireccional)
        // Esto prueba que dentro de addInscripcion() existe la línea: $inscripcion->setActividad($this);
        $this->assertSame($actividad, $inscripcion->getActividad());

        // 3. Eliminar Inscripción
        $actividad->removeInscripcion($inscripcion);

        // ASERCIÓN 3: La actividad ya no tiene la inscripción
        $this->assertCount(0, $actividad->getInscripciones());
        $this->assertFalse($actividad->getInscripciones()->contains($inscripcion));

        // ASERCIÓN 4: La inscripción ya no apunta a la actividad
        // Esto prueba que dentro de removeInscripcion() existe la lógica de limpieza
        $this->assertNull($inscripcion->getActividad());
    }

    public function testSetOrganizacion(): void
    {
        $actividad = new Actividad();
        $organizacion = new Organizacion();
        $organizacion->setNombre('ONG Prueba');

        $actividad->setOrganizacion($organizacion);

        $this->assertSame($organizacion, $actividad->getOrganizacion());
        $this->assertEquals('ONG Prueba', $actividad->getOrganizacion()->getNombre());
    }
}

