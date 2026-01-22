<?php

namespace App\Model\Idioma;

use App\Entity\Idioma;

class IdiomaDTO
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $codigo_iso 
    ) {}

    public static function fromEntity(Idioma $idioma): self
    {
        return new self(
            $idioma->getId(),
            $idioma->getNombre(),
            $idioma->getCodigoIso()
        );
    }
}
