<?php

namespace App\Model\Coordinador;

use App\Entity\Coordinador;

class CoordinadorResponseDTO
{
    public function __construct(
        public int $id,
        public ?string $nombre,
        public ?string $apellidos,
        public ?string $telefono,
        public string $rol,
        public string $correo,
        public string $estado_cuenta
    ) {}

    public static function fromEntity(Coordinador $coord): self
    {
        return new self(
            $coord->getUsuario()->getId(),
            $coord->getNombre(),
            $coord->getApellidos(),
            $coord->getTelefono(),
            $coord->getUsuario()->getRol()->getNombre(),
            $coord->getUsuario()->getCorreo(),
            $coord->getUsuario()->getEstadoCuenta()
        );
    }
}