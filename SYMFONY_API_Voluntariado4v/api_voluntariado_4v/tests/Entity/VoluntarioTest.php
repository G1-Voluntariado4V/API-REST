<?php

namespace App\Tests\Entity;

use App\Entity\Curso;
use App\Entity\Voluntario;
use App\Entity\VoluntarioIdioma;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;

class VoluntarioTest extends TestCase
{
    public function testInicializacionColecciones(): void
    {
        $voluntario = new Voluntario();

        // 1. Verificar que todas las listas empiezan vacías en lugar de null
        // Esto prueba que el constructor hace: $this->coleccion = new ArrayCollection();
        $this->assertCount(0, $voluntario->getVoluntarioIdiomas());
        $this->assertCount(0, $voluntario->getInscripciones());
        $this->assertCount(0, $voluntario->getPreferencias());
        
        // 2. Verificar valores booleanos por defecto
        $this->assertFalse($voluntario->isCarnetConducir());
    }

    public function testRelacionConUsuario(): void
    {
        $usuario = new Usuario();
        
        // Simular que el usuario tiene un email (aunque el test unitario no guarda en BD)
        // Usamos Reflection o mock si la clase Usuario no tiene setters públicos para testear,
        // pero aquí solo probamos la vinculación.
        
        $voluntario = new Voluntario();
        $voluntario->setUsuario($usuario);

        $this->assertSame($usuario, $voluntario->getUsuario());
    }

    public function testRelacionConCurso(): void
    {
        $voluntario = new Voluntario();
        $curso = new Curso();
        $curso->setNombre("Desarrollo de Aplicaciones Multiplataforma");

        $voluntario->setCursoActual($curso);

        $this->assertSame($curso, $voluntario->getCursoActual());
        $this->assertEquals("Desarrollo de Aplicaciones Multiplataforma", $voluntario->getCursoActual()->getNombre());
    }

    public function testGestionIdiomasBidireccional(): void
    {
        $voluntario = new Voluntario();
        $idiomaRelacion = new VoluntarioIdioma();
        
        // TEST DE AÑADIR
        $voluntario->addVoluntarioIdioma($idiomaRelacion);
        
        // Verificar que el voluntario tiene el idioma en su lista
        $this->assertTrue($voluntario->getVoluntarioIdiomas()->contains($idiomaRelacion));
        
        // VERIFICAR MAGIA: El idioma 'apunta' automáticamente de vuelta al voluntario
        // Esto prueba: $vi->setVoluntario($this) dentro de addVoluntarioIdioma
        $this->assertSame($voluntario, $idiomaRelacion->getVoluntario());

        // TEST DE BORRAR
        $voluntario->removeVoluntarioIdioma($idiomaRelacion);
        
        // Verificar que ya no está en la lista
        $this->assertFalse($voluntario->getVoluntarioIdiomas()->contains($idiomaRelacion));
        
        // VERIFICAR MAGIA: El idioma deja de apuntar al voluntario
        $this->assertNull($idiomaRelacion->getVoluntario());
    }
}
