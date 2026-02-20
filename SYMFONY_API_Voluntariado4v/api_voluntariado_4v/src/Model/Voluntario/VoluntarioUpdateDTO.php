<?php

namespace App\Model\Voluntario;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

class VoluntarioUpdateDTO
{
    #[Assert\NotBlank(message: "El nombre no puede estar vacío.")]
    #[Assert\Length(min: 2, max: 50)]
    #[OA\Property(example: "Ana María")]
    public string $nombre;

    #[Assert\NotBlank(message: "Los apellidos son obligatorios.")]
    #[OA\Property(example: "García Pérez")]
    public string $apellidos;

    #[Assert\Regex(pattern: "/^[0-9+ ]+$/", message: "El teléfono solo puede contener números.")]
    #[OA\Property(example: "+34 655 44 33 22")]
    public ?string $telefono = null;

    #[Assert\Date(message: "El formato debe ser YYYY-MM-DD.")]
    #[OA\Property(example: "1999-12-31")]
    public ?string $fechaNac = null;

    #[OA\Property(example: true)]
    public ?bool $carnet_conducir = null;

    #[Assert\Length(max: 500, maxMessage: "La descripción no puede tener más de 500 caracteres")]
    #[OA\Property(example: "Actualización de mi descripción...")]
    public ?string $descripcion = null;

    #[OA\Property(example: [2, 3], type: 'array', items: new OA\Items(type: 'integer'))]
    public ?array $preferencias_ids = null;

    #[OA\Property(example: 3)]
    public ?int $id_curso_actual = null;

    #[OA\Property(
        description: "Idiomas del voluntario",
        type: "array",
        items: new OA\Items(
            properties: [
                new OA\Property(property: "id_idioma", type: "integer", example: 1),
                new OA\Property(property: "nivel", type: "string", example: "B2")
            ]
        )
    )]
    public ?array $idiomas = null;
}
