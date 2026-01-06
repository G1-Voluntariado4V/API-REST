<?php

namespace App\Model\Organizacion;

use Symfony\Component\Validator\Constraints as Assert;

class OrganizacionUpdateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El nombre no puede estar vacío")]
        #[Assert\Length(min: 2, max: 100)]
        public string $nombre,

        #[Assert\NotBlank(message: "La descripción es obligatoria")]
        public string $descripcion,

        #[Assert\Url(message: "La web debe ser una URL válida")]
        public ?string $sitioWeb = null,

        public ?string $direccion = null,

        #[Assert\Regex(pattern: "/^[0-9\-\+\s]+$/", message: "Teléfono no válido")]
        public ?string $telefono = null
    ) {}
}
