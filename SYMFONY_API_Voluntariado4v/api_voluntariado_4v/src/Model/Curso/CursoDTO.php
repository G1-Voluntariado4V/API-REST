<?php

namespace App\Model\Curso;

use App\Entity\Curso;

class CursoDTO
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $abreviacion,
        public string $grado, 
        public int $nivel
    ) {}

    public static function fromEntity(Curso $curso): self
    {
        return new self(
            $curso->getId(),
            $curso->getNombre(),
            $curso->getAbreviacion(),
            $curso->getGrado(),
            $curso->getNivel()
        );
    }
}