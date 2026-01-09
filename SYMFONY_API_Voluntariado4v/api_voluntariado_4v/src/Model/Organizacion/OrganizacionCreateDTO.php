<?php

namespace App\Model\Organizacion;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO para la creación completa de una organización
 * Incluye datos de Usuario + Organización
 */
class OrganizacionCreateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El Google ID es obligatorio")]
        public string $google_id,

        #[Assert\NotBlank]
        #[Assert\Email]
        public string $correo,

        #[Assert\NotBlank(message: "El nombre no puede estar vacío")]
        #[Assert\Length(min: 2, max: 100)]
        public string $nombre,

        #[Assert\NotBlank(message: "El CIF es obligatorio")]
        public string $cif,

        #[Assert\NotBlank(message: "La descripción es obligatoria")]
        public string $descripcion,

        #[Assert\Url(message: "La web debe ser una URL válida", requireTld: true)]
        public ?string $sitioWeb = null,

        public ?string $direccion = null,

        #[Assert\Regex(pattern: "/^[0-9\-\+\s]+$/", message: "Teléfono no válido")]
        public ?string $telefono = null
    ) {}
}
