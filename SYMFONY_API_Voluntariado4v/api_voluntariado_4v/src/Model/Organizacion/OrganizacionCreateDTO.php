<?php

namespace App\Model\Organizacion;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

class OrganizacionCreateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El Google ID es obligatorio")]
        #[OA\Property(example: "google_ong_123")]
        public string $google_id,

        #[Assert\NotBlank]
        #[Assert\Email]
        #[OA\Property(example: "contacto@ong-ejemplo.org")]
        public string $correo,

        #[Assert\NotBlank(message: "El nombre no puede estar vacío")]
        #[Assert\Length(min: 2, max: 100)]
        #[OA\Property(example: "ONG Ejemplo")]
        public string $nombre,

        #[Assert\NotBlank(message: "El CIF es obligatorio")]
        #[OA\Property(example: "G12345678")]
        public string $cif,

        #[Assert\NotBlank(message: "La descripción es obligatoria")]
        #[OA\Property(example: "Organización dedicada a la ayuda humanitaria.")]
        public string $descripcion,

        #[Assert\Url(message: "La web debe ser una URL válida", requireTld: true)]
        #[OA\Property(example: "https://ong-ejemplo.org")]
        public ?string $sitioWeb = null,

        #[OA\Property(example: "Calle Solidaridad 12")]
        public ?string $direccion = null,

        #[Assert\Regex(pattern: "/^[0-9\-\+\s]+$/", message: "Teléfono no válido")]
        #[OA\Property(example: "+34 91 123 45 67")]
        public ?string $telefono = null
    ) {}
}
