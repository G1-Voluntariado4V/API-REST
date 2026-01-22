<?php

namespace App\Model\Organizacion;

use App\Entity\Organizacion;
use OpenApi\Attributes as OA;

class OrganizacionResponseDTO
{
    public function __construct(
        #[OA\Property(example: 5)]
        public int $id,

        #[OA\Property(example: "ONG Ejemplo")]
        public string $nombre,

        #[OA\Property(example: "Organización dedicada a la ayuda humanitaria.")]
        public string $descripcion,

        #[OA\Property(example: "contacto@ong-ejemplo.org")]
        public string $email,

        #[OA\Property(example: "+34 91 123 45 67")]
        public ?string $telefono,

        #[OA\Property(example: "Calle Solidaridad 12")]
        public ?string $direccion,

        #[OA\Property(example: "https://ong-ejemplo.org")]
        public ?string $web,

        #[OA\Property(example: "G12345678")]
        public string $cif
    ) {}

    public static function fromEntity(Organizacion $org): self
    {
        $usuario = $org->getUsuario();

        return new self(
            $org->getUsuario()->getId(),
            $org->getNombre(),
            $org->getDescripcion() ?? '',
            $usuario->getCorreo(),
            $org->getTelefono(),
            $org->getDireccion(),
            $org->getSitioWeb(),
            $org->getCif() ?? ''
        );
    }
}
