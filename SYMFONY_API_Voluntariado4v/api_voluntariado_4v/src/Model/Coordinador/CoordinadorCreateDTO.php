<?php

namespace App\Model\Coordinador;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

class CoordinadorCreateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El nombre es obligatorio")]
        #[OA\Property(example: "Juan")]
        public string $nombre,

        #[Assert\NotBlank(message: "El correo es obligatorio")]
        #[Assert\Email(message: "El correo no es válido")]
        #[OA\Property(example: "coordinador@escuela.org")]
        public string $correo,

        #[Assert\NotBlank]
        #[OA\Property(example: "google_coord_123")]
        public string $google_id,

        #[OA\Property(example: "Pérez")]
        public ?string $apellidos = null,

        #[OA\Property(example: "+34 600 11 22 33")]
        public ?string $telefono = null
    ) {}
}
