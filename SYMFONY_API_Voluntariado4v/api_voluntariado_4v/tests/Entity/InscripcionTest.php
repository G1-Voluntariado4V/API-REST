<?php

namespace App\Tests\Entity;

use App\Entity\Inscripcion;
use PHPUnit\Framework\TestCase;

class InscripcionTest extends TestCase
{
    public function testConstructorInicializaFechaYEstado(): void
    {
        $inscripcion = new Inscripcion();

        // Verificar estado por defecto
        $this->assertEquals('Pendiente', $inscripcion->getEstadoSolicitud());

        // Verificar que fechaSolicitud es una instancia de DateTime y es reciente
        $this->assertInstanceOf(\DateTimeInterface::class, $inscripcion->getFechaSolicitud());
        
        // Comprobar que la fecha es "ahora" (con un margen de 5 segundo de diferencia)
        $ahora = new \DateTime();
        $diferencia = $ahora->getTimestamp() - $inscripcion->getFechaSolicitud()->getTimestamp();
        
        $this->assertLessThan(5, abs($diferencia), 'La fecha de solicitud debería ser el momento actual');
    }
}
