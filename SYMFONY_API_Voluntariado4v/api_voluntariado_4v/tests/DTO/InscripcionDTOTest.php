<?php

namespace App\Tests\DTO;

use App\Model\Inscripcion\InscripcionUpdateDTO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Tests unitarios para los DTOs de Inscripcion
 */
class InscripcionDTOTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    // ========================================================================
    // TESTS DE InscripcionUpdateDTO
    // ========================================================================

    public function testInscripcionUpdateDTOValidoConAceptada(): void
    {
        $dto = new InscripcionUpdateDTO(estado: 'Aceptada');

        $violations = $this->validator->validate($dto);
        $this->assertCount(0, $violations);
    }

    public function testInscripcionUpdateDTOValidoConRechazada(): void
    {
        $dto = new InscripcionUpdateDTO(estado: 'Rechazada');

        $violations = $this->validator->validate($dto);
        $this->assertCount(0, $violations);
    }

    public function testInscripcionUpdateDTOValidoConPendiente(): void
    {
        $dto = new InscripcionUpdateDTO(estado: 'Pendiente');

        $violations = $this->validator->validate($dto);
        $this->assertCount(0, $violations);
    }

    public function testInscripcionUpdateDTOEstadoInvalido(): void
    {
        // El validator debería rechazar estados que no están en el enum
        $dto = new InscripcionUpdateDTO(estado: 'EstadoInvalido');

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));
    }

    public function testInscripcionUpdateDTOEstadoVacio(): void
    {
        $dto = new InscripcionUpdateDTO(estado: '');

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));
    }

    // ========================================================================
    // TESTS DE PROPIEDADES
    // ========================================================================

    public function testInscripcionUpdateDTOPropiedadAccesible(): void
    {
        $dto = new InscripcionUpdateDTO(estado: 'Aceptada');

        $this->assertEquals('Aceptada', $dto->estado);
    }

    public function testInscripcionUpdateDTOTodosLosEstadosValidos(): void
    {
        $estadosValidos = ['Aceptada', 'Rechazada', 'Pendiente'];

        foreach ($estadosValidos as $estado) {
            $dto = new InscripcionUpdateDTO(estado: $estado);
            $violations = $this->validator->validate($dto);

            $this->assertCount(0, $violations, "El estado '{$estado}' debería ser válido");
        }
    }
}
