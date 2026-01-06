<?php

namespace App\Model\Coordinador;

use Symfony\Component\Validator\Constraints as Assert;

class CoordinadorUpdateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El nombre no puede estar vacío")]
        #[Assert\Length(min: 2, max: 50)]
        public string $nombre,

        #[Assert\Length(min: 2, max: 50)]
        public ?string $apellidos = null,

        #[Assert\Regex(pattern: "/^[0-9\s\+]+$/", message: "El teléfono solo puede contener números y espacios")]
        public ?string $telefono = null
    ) {}
}
