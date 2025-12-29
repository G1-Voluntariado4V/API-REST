<?php

namespace App\Model\Actividad;

use App\Model\Ods\OdsDTO;
use App\Model\TipoVoluntariado\TipoVoluntariadoDTO;

class ActividadResponseDTO
{
    public function __construct(
        public int $id,
        public string $titulo,
        public ?string $descripcion,
        public string $fecha_inicio,
        public int $duracion_horas,
        public int $cupo_maximo,

        // Campo calculado útil para la barra de progreso en el Frontend
        public int $inscritos_confirmados,

        public string $ubicacion,
        public string $estado_publicacion,

        // Información de la organización para no enviar solo el ID
        public string $nombre_organizacion,
        public ?string $img_organizacion,

        /** @var OdsDTO[] */
        public array $ods = [],

        /** @var TipoVoluntariadoDTO[] */
        public array $tipos = []
    ) {}
}
