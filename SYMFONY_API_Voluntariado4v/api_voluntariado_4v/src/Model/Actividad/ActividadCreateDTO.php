<?php

namespace App\Model\Actividad;

use App\Model\Ods\OdsDTO;
use App\Model\TipoVoluntariado\TipoVoluntariadoDTO;
use Symfony\Component\Validator\Constraints as Assert;

class ActividadCreateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El título no puede estar vacío")]
        public string $titulo,

        public ?string $descripcion,

        #[Assert\NotBlank]
        #[Assert\DateTime(message: "La fecha debe tener un formato válido")]
        public string $fecha_inicio,

        #[Assert\Positive(message: "La duración debe ser mayor a 0")]
        public int $duracion_horas,

        #[Assert\Positive(message: "El cupo debe ser mayor a 0")]
        public int $cupo_maximo,

        public string $ubicacion,

        #[Assert\NotNull]
        public int $id_organizacion,

        /** @var OdsDTO[] */
        public array $odsIds = [],

        /** @var TipoVoluntariadoDTO[] */
        public array $tiposIds = []
    ) {
    }
}