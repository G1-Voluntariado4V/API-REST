<?php

namespace App\Model\Coordinador;

use App\Entity\Coordinador;
use OpenApi\Attributes as OA;

class CoordinadorResponseDTO
{
    public function __construct(
        #[OA\Property(example: 34)]
        public int $id_usuario,

        #[OA\Property(example: "Juan")]
        public string $nombre,

        #[OA\Property(example: "Pérez")]
        public ?string $apellidos,

        #[OA\Property(example: "+34 600 11 22 33")]
        public ?string $telefono,

        #[OA\Property(example: "coordinador@escuela.org")]
        public string $correo,

        #[OA\Property(example: "/uploads/usuarios/usr_34.png")]
        public ?string $img_perfil
    ) {}

    public static function fromEntity(Coordinador $coord): self
    {
        $usuario = $coord->getUsuario();
        return new self(
            id_usuario: $usuario->getId(),
            nombre: $coord->getNombre() ?? '',
            apellidos: $coord->getApellidos(),
            telefono: $coord->getTelefono(),
            correo: $usuario->getCorreo(),
            img_perfil: $usuario->getImgPerfilUrl()
        );
    }
}
