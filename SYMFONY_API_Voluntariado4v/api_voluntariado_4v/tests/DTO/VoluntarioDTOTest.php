<?php

namespace App\Tests\DTO;

use App\Model\Voluntario\VoluntarioCreateDTO;
use App\Model\Voluntario\VoluntarioUpdateDTO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Tests unitarios para los DTOs de Voluntario
 */
class VoluntarioDTOTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    // ========================================================================
    // TESTS DE VoluntarioCreateDTO
    // ========================================================================

    public function testVoluntarioCreateDTOValido(): void
    {
        $dto = new VoluntarioCreateDTO(
            google_id: '123456789',
            correo: 'voluntario@test.com',
            nombre: 'Juan',
            apellidos: 'García López',
            dni: '12345678A',
            telefono: '612345678',
            fecha_nac: '1990-05-15',
            carnet_conducir: false,
            id_curso_actual: 1,
            descripcion: 'Voluntario test',
            preferencias_ids: [],
            idiomas: []
        );

        $violations = $this->validator->validate($dto);

        // Filtrar solo violaciones de campos obligatorios
        $criticalViolations = 0;
        foreach ($violations as $violation) {
            $message = $violation->getMessage();
            if (
                str_contains($message, 'obligatorio') ||
                str_contains($message, 'vacío') ||
                str_contains($message, 'blank')
            ) {
                $criticalViolations++;
            }
        }

        $this->assertEquals(0, $criticalViolations, 'No debería haber violaciones de campos obligatorios');
    }

    public function testVoluntarioCreateDTONombreVacio(): void
    {
        $dto = new VoluntarioCreateDTO(
            google_id: '123456789',
            correo: 'voluntario@test.com',
            nombre: '',
            apellidos: 'García',
            dni: '12345678A',
            telefono: '612345678',
            fecha_nac: '1990-05-15',
            carnet_conducir: false,
            id_curso_actual: 1
        );

        $violations = $this->validator->validate($dto);

        $nombreViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'nombre') {
                $nombreViolation = true;
                break;
            }
        }

        $this->assertTrue($nombreViolation, 'Debería haber una violación para nombre vacío');
    }

    public function testVoluntarioCreateDTOApellidosVacios(): void
    {
        $dto = new VoluntarioCreateDTO(
            google_id: '123456789',
            correo: 'voluntario@test.com',
            nombre: 'Juan',
            apellidos: '',
            dni: '12345678A',
            telefono: '612345678',
            fecha_nac: '1990-05-15',
            carnet_conducir: false,
            id_curso_actual: 1
        );

        $violations = $this->validator->validate($dto);

        $apellidosViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'apellidos') {
                $apellidosViolation = true;
                break;
            }
        }

        $this->assertTrue($apellidosViolation, 'Debería haber una violación para apellidos vacíos');
    }

    public function testVoluntarioCreateDTOCorreoInvalido(): void
    {
        $dto = new VoluntarioCreateDTO(
            google_id: '123456789',
            correo: 'correo-invalido',
            nombre: 'Juan',
            apellidos: 'García',
            dni: '12345678A',
            telefono: '612345678',
            fecha_nac: '1990-05-15',
            carnet_conducir: false,
            id_curso_actual: 1
        );

        $violations = $this->validator->validate($dto);

        $correoViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'correo') {
                $correoViolation = true;
                break;
            }
        }

        $this->assertTrue($correoViolation, 'Debería haber una violación para correo inválido');
    }

    public function testVoluntarioCreateDTOGoogleIdVacio(): void
    {
        $dto = new VoluntarioCreateDTO(
            google_id: '',
            correo: 'voluntario@test.com',
            nombre: 'Juan',
            apellidos: 'García',
            dni: '12345678A',
            telefono: '612345678',
            fecha_nac: '1990-05-15',
            carnet_conducir: false,
            id_curso_actual: 1
        );

        $violations = $this->validator->validate($dto);

        $googleIdViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'google_id') {
                $googleIdViolation = true;
                break;
            }
        }

        $this->assertTrue($googleIdViolation, 'Debería haber una violación para google_id vacío');
    }

    // ========================================================================
    // TESTS DE VoluntarioUpdateDTO - No usa constructor
    // ========================================================================

    public function testVoluntarioUpdateDTOValido(): void
    {
        $dto = new VoluntarioUpdateDTO();
        $dto->nombre = 'Juan Actualizado';
        $dto->apellidos = 'García López Actualizado';
        $dto->telefono = '612345678';
        $dto->descripcion = 'Nueva descripción';

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations, 'No debería haber violaciones');
    }

    public function testVoluntarioUpdateDTONombreRequerido(): void
    {
        $dto = new VoluntarioUpdateDTO();
        $dto->nombre = '';
        $dto->apellidos = 'García';

        $violations = $this->validator->validate($dto);

        $nombreViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'nombre') {
                $nombreViolation = true;
                break;
            }
        }

        $this->assertTrue($nombreViolation, 'Debería haber una violación para nombre vacío');
    }

    // ========================================================================
    // TESTS DE PROPIEDADES
    // ========================================================================

    public function testVoluntarioCreateDTOPropiedadesAccesibles(): void
    {
        $dto = new VoluntarioCreateDTO(
            google_id: '123456789',
            correo: 'test@test.com',
            nombre: 'Juan',
            apellidos: 'García',
            dni: '12345678A',
            telefono: '612345678',
            fecha_nac: '1990-05-15',
            carnet_conducir: true,
            id_curso_actual: 1
        );

        $this->assertEquals('123456789', $dto->google_id);
        $this->assertEquals('test@test.com', $dto->correo);
        $this->assertEquals('Juan', $dto->nombre);
        $this->assertEquals('García', $dto->apellidos);
        $this->assertEquals('12345678A', $dto->dni);
        $this->assertEquals('612345678', $dto->telefono);
        $this->assertTrue($dto->carnet_conducir);
    }
}
