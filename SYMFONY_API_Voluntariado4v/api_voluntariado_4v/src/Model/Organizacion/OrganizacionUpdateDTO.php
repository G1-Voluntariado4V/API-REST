<?php

namespace App\Model\Organizacion;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

class OrganizacionUpdateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El nombre no puede estar vacío")]
        #[Assert\Length(min: 2, max: 100)]
        #[OA\Property(example: "ONG Ejemplo Actualizada")]
        public string $nombre,

        #[Assert\NotBlank(message: "La descripción es obligatoria")]
        #[OA\Property(example: "Nueva descripción de la organización...")]
        public string $descripcion,

        #[Assert\Url(message: "La web debe ser una URL válida", requireTld: true)]
        #[OA\Property(example: "https://ong-ejemplo.org")]
        public ?string $sitioWeb = null,

        #[OA\Property(example: "Avenida de la Paz 45")]
        public ?string $direccion = null,

        #[Assert\Regex(pattern: "/^[0-9\-\+\s]+$/", message: "Teléfono no válido")]
        #[OA\Property(example: "+34 91 987 65 43")]
        public ?string $telefono = null
    ) {}
}
