<?php
// <!-- DTO para la respuesta de un voluntario: Lo que manda el backend -->

namespace App\Model\Voluntario;

use App\Entity\Voluntario;
use OpenApi\Attributes as OA;

class VoluntarioResponseDTO
{
    public function __construct(
        #[OA\Property(example: 10)]
        public int $id_usuario,

        #[OA\Property(example: "Ana")]
        public string $nombre,

        #[OA\Property(example: "García")]
        public string $apellidos,

        #[OA\Property(example: "Ana García")]
        public string $nombre_completo,

        #[OA\Property(example: "ana@test.com")]
        public string $correo,

        #[OA\Property(example: "usr_10_pic.jpg", nullable: true)]
        public ?string $img_perfil,

        #[OA\Property(example: "12345678Z")]
        public ?string $dni,

        #[OA\Property(example: "+34 600 00 00 00")]
        public ?string $telefono,

        #[OA\Property(example: "1995-05-20")]
        public ?string $fecha_nac,

        #[OA\Property(example: true)]
        public bool $carnet_conducir,

        #[OA\Property(example: 2)]
        public ?int $id_curso,

        #[OA\Property(example: "2º DAM")]
        public string $curso,

        #[OA\Property(example: "Activa")]
        public string $estado_cuenta,

        #[OA\Property(example: "Me encanta participar en actvidades sociales.")]
        public ?string $descripcion,

        #[OA\Property(
            description: "Tipos de voluntariado preferidos",
            type: "array",
            items: new OA\Items(type: "string", example: "Social")
        )]
        public array $preferencias,

        #[OA\Property(
            description: "Idiomas del voluntario",
            type: "array",
            items: new OA\Items(
                properties: [
                    new OA\Property(property: "id_idioma", type: "integer", example: 1),
                    new OA\Property(property: "idioma", type: "string", example: "Inglés"),
                    new OA\Property(property: "nivel", type: "string", example: "B2")
                ]
            )
        )]
        public array $idiomas
    ) {}

    /**
     * Mapeador: Convierte la Entidad compleja en este DTO simple
     */
    public static function fromEntity(Voluntario $voluntario): self
    {
        // 1. Aplanar Preferencias (TIPOS DE VOLUNTARIADO)
        $prefs = [];
        foreach ($voluntario->getPreferencias() as $tipoVoluntariado) {
            $prefs[] = $tipoVoluntariado->getNombreTipo();
        }

        // 2. Aplanar Idiomas
        $idiomasList = [];
        foreach ($voluntario->getVoluntarioIdiomas() as $vi) {
            $idiomasList[] = [
                'id_idioma' => $vi->getIdioma()->getId(),
                'idioma' => $vi->getIdioma()->getNombre(),
                'nivel'  => $vi->getNivel()
            ];
        }

        // 3. Obtener datos del Usuario padre
        $usuario = $voluntario->getUsuario();

        return new self(
            id_usuario: $usuario->getId(),
            nombre: $voluntario->getNombre(),
            apellidos: $voluntario->getApellidos(),
            nombre_completo: $voluntario->getNombre() . ' ' . $voluntario->getApellidos(),
            correo: $usuario->getCorreo(),
            img_perfil: $usuario->getImgPerfil(),
            dni: $voluntario->getDni(),
            telefono: $voluntario->getTelefono(),
            fecha_nac: $voluntario->getFechaNac()?->format('Y-m-d'),
            carnet_conducir: (bool) $voluntario->isCarnetConducir(),
            id_curso: $voluntario->getCursoActual() ? $voluntario->getCursoActual()->getId() : null,
            curso: $voluntario->getCursoActual() ? $voluntario->getCursoActual()->getAbreviacion() : 'Sin asignar',
            estado_cuenta: $usuario->getEstadoCuenta(),
            descripcion: $voluntario->getDescripcion(),
            preferencias: $prefs,
            idiomas: $idiomasList
        );
    }
}
