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

    public function testInscripcionUpdateDTOEstadoAceptada(): void
    {
        $dto = new InscripcionUpdateDTO(estado: 'Aceptada');

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations, 'No debería haber violación para estado Aceptada');
    }

    public function testInscripcionUpdateDTOEstadoRechazada(): void
    {
        $dto = new InscripcionUpdateDTO(estado: 'Rechazada');

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations, 'No debería haber violación para estado Rechazada');
    }

    public function testInscripcionUpdateDTOEstadoPendiente(): void
    {
        $dto = new InscripcionUpdateDTO(estado: 'Pendiente');

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations, 'No debería haber violación para estado Pendiente');
    }

    public function testInscripcionUpdateDTOEstadoInvalido(): void
    {
        $dto = new InscripcionUpdateDTO(estado: 'EstadoInvalido');

        $violations = $this->validator->validate($dto);

        $estadoViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'estado') {
                $estadoViolation = true;
                break;
            }
        }

        $this->assertTrue($estadoViolation, 'Debería haber violación para estado inválido');
    }

    public function testInscripcionUpdateDTOEstadoVacio(): void
    {
        $dto = new InscripcionUpdateDTO(estado: '');

        $violations = $this->validator->validate($dto);

        $estadoViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'estado') {
                $estadoViolation = true;
                break;
            }
        }

        $this->assertTrue($estadoViolation, 'Debería haber violación para estado vacío');
    }

    // Probar los valores exactos que no son válidos
    public function testInscripcionUpdateDTOEstadoAceptadoSinA(): void
    {
        // "Aceptado" (sin 'a' final) debería ser inválido
        $dto = new InscripcionUpdateDTO(estado: 'Aceptado');

        $violations = $this->validator->validate($dto);

        $estadoViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'estado') {
                $estadoViolation = true;
                break;
            }
        }

        $this->assertTrue($estadoViolation, 'Aceptado (sin a) debería ser inválido');
    }

    // ========================================================================
    // TESTS DE PROPIEDADES
    // ========================================================================

    public function testInscripcionUpdateDTOPropiedadAccesible(): void
    {
        $dto = new InscripcionUpdateDTO(estado: 'Aceptada');

        $this->assertEquals('Aceptada', $dto->estado);
    }
}
