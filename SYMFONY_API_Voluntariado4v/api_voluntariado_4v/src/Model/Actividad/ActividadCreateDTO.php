<?php

namespace App\Model\Actividad;

use App\Model\Ods\OdsDTO;
use App\Model\TipoVoluntariado\TipoVoluntariadoDTO;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

class ActividadCreateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El título no puede estar vacío")]
        #[OA\Property(example: "Limpieza de Playa")]
        public string $titulo,

        #[OA\Property(example: "Actividad de recogida de residuos en la playa para concienciar sobre el medio ambiente.")]
        public ?string $descripcion,

        #[Assert\NotBlank]
        #[Assert\DateTime(message: "La fecha debe tener un formato válido")]
        #[OA\Property(example: "2026-06-15 09:00:00")]
        public string $fecha_inicio,

        #[Assert\Positive(message: "La duración debe ser mayor a 0")]
        #[OA\Property(example: 4)]
        public int $duracion_horas,

        #[Assert\Positive(message: "El cupo debe ser mayor a 0")]
        #[OA\Property(example: 50)]
        public int $cupo_maximo,

        #[OA\Property(example: "Playa de la Barceloneta")]
        public string $ubicacion,

        #[Assert\NotNull]
        #[OA\Property(example: 5)]
        public int $id_organizacion,

        /** @var int[] */
        #[OA\Property(example: [13, 14], type: 'array', items: new OA\Items(type: 'integer'))]
        public array $odsIds = [],

        /** @var TipoVoluntariadoDTO[] */
        #[Assert\Count(min: 1, minMessage: "Debes seleccionar al menos un tipo de voluntariado")]
        #[OA\Property(example: [1], type: 'array', items: new OA\Items(type: 'integer'))]
        public array $tiposIds = []
    ) {}
}
