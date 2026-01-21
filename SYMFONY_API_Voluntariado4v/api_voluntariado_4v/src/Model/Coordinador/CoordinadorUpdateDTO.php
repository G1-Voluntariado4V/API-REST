<?php

namespace App\Model\Coordinador;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

class CoordinadorUpdateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El nombre no puede estar vacío")]
        #[Assert\Length(min: 2, max: 50)]
        #[OA\Property(example: "Juan")]
        public string $nombre,

        #[Assert\Length(min: 2, max: 50)]
        #[OA\Property(example: "Pérez")]
        public ?string $apellidos = null,

        #[Assert\Regex(pattern: "/^[0-9\s\+]+$/", message: "El teléfono solo puede contener números y espacios")]
        #[OA\Property(example: "+34 600 11 22 33")]
        public ?string $telefono = null
    ) {}
}
