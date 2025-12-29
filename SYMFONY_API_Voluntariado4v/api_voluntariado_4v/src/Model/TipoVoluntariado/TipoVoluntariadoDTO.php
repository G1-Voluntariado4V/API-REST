<?php

namespace App\Model\TipoVoluntariado;

class TipoVoluntariadoDTO
{
    public function __construct(
        // Usamos el ID de la tabla TIPO_VOLUNTARIADO
        public int $id,

        // El nombre será útil para mostrarlo en el Frontend
        public ?string $nombre = null
    ) {}
}
