<?php
// <!-- DTO para la respuesta de un voluntario: Lo que manda el backend -->

namespace App\Model\Voluntario;

use App\Entity\Voluntario;

class VoluntarioResponseDTO
{
    public function __construct(
        public int $id_usuario,
        public string $nombre,
        public string $apellidos,
        public string $nombre_completo,
        public string $correo,
        public ?string $dni,
        public ?string $telefono,
        public ?string $fecha_nac,
        public bool $carnet_conducir,
        public string $curso,
        public string $estado_cuenta,
        public ?string $descripcion,
        public array $preferencias,
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
            dni: $voluntario->getDni(),
            telefono: $voluntario->getTelefono(),
            fecha_nac: $voluntario->getFechaNac()?->format('Y-m-d'),
            carnet_conducir: (bool) $voluntario->isCarnetConducir(),
            curso: $voluntario->getCursoActual() ? $voluntario->getCursoActual()->getAbreviacion() : 'Sin asignar',
            estado_cuenta: $usuario->getEstadoCuenta(),
            descripcion: $voluntario->getDescripcion(),
            preferencias: $prefs,
            idiomas: $idiomasList
        );
    }
}

