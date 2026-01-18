<?php

namespace App\Model\Actividad;

use Symfony\Component\Validator\Constraints as Assert;

class ActividadUpdateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El título no puede estar vacío")]
        #[Assert\Length(min: 5, max: 150)]
        public string $titulo,

        public ?string $descripcion,

        #[Assert\NotBlank]
        public string $ubicacion,

        #[Assert\NotBlank]
        #[Assert\DateTime] // Acepta strings válidos como "2026-05-10 10:00:00"
        public string $fecha_inicio,

        #[Assert\Positive]
        public int $duracion_horas,

        #[Assert\Positive]
        public int $cupo_maximo,

        // Relaciones (Solo los IDs necesarios para sincronizar)
        // NOTA: No incluimos id_organizacion aquí

        #[Assert\All([new Assert\Type('integer')])]
        public array $odsIds = [],

        #[Assert\All([new Assert\Type('integer')])]
        public array $tiposIds = []
    ) {}
}
