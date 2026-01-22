<?php

namespace App\Model\Actividad;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

class ActividadUpdateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El título no puede estar vacío")]
        #[Assert\Length(min: 5, max: 150)]
        #[OA\Property(example: "Limpieza de Playa Revisada")]
        public string $titulo,

        #[OA\Property(example: "Actualización de la descripción de la actividad.")]
        public ?string $descripcion,

        #[Assert\NotBlank]
        #[OA\Property(example: "Nueva Ubicación, 45")]
        public string $ubicacion,

        #[Assert\NotBlank]
        #[Assert\DateTime]
        #[OA\Property(example: "2026-06-16 10:00:00")]
        public string $fecha_inicio,

        #[Assert\Positive]
        #[OA\Property(example: 5)]
        public int $duracion_horas,

        #[Assert\Positive]
        #[OA\Property(example: 60)]
        public int $cupo_maximo,

        #[Assert\All([new Assert\Type('integer')])]
        #[OA\Property(example: [13], type: 'array', items: new OA\Items(type: 'integer'))]
        public array $odsIds = [],

        #[Assert\All([new Assert\Type('integer')])]
        #[Assert\Count(min: 1, minMessage: "Debes seleccionar al menos un tipo de voluntariado")]
        #[OA\Property(example: [1, 2], type: 'array', items: new OA\Items(type: 'integer'))]
        public array $tiposIds = []
    ) {}
}
