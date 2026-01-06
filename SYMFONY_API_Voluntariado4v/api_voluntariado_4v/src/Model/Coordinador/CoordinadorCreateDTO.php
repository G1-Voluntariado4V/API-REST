<?php

namespace App\Model\Coordinador;

use Symfony\Component\Validator\Constraints as Assert;

class CoordinadorCreateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El nombre es obligatorio")]
        public string $nombre,

        #[Assert\NotBlank(message: "El correo es obligatorio")]
        #[Assert\Email(message: "El correo no es válido")]
        public string $correo,

        #[Assert\NotBlank]
        public string $google_id,

        public ?string $apellidos = null,
        public ?string $telefono = null
    ) {}
}