<?php

namespace App\Model\TipoVoluntariado;

class TipoVoluntariadoDTO
{
    public function __construct(
        public int $id,
        public ?string $nombre = null
    ) {}
}
