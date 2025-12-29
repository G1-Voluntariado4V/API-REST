<?php

namespace App\Model\Ods;

class OdsDTO
{
    public function __construct(
        // El ID es obligatorio para identificarlo en la tabla ODS
        public int $id,

        // El nombre es opcional (null) para cuando solo enviamos datos al servidor
        public ?string $nombre = null
    ) {
    }
}