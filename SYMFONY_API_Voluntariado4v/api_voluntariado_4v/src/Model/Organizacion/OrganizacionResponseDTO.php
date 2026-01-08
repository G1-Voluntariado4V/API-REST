<?php

namespace App\Model\Organizacion;

use App\Entity\Organizacion;

class OrganizacionResponseDTO
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $descripcion,
        public string $email,       // Viene de Usuario
        public ?string $telefono,
        public ?string $direccion,
        public ?string $web,
        public string $cif          // Dato sensible
    ) {}

    public static function fromEntity(Organizacion $org): self
    {
        // Recuerda: Organizacion tiene relacion 1a1 con Usuario
        $usuario = $org->getUsuario();

        return new self(
            $org->getUsuario()->getId(), // El ID es compartido (PK compartida)
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
