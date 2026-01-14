<?php
// <!-- DTO para la respuesta de un voluntario: Lo que manda el backend -->

namespace App\Model\Voluntario;

use App\Entity\Voluntario;

class VoluntarioResponseDTO
{
    public function __construct(
        public int $id_usuario,
        public string $nombre_completo,
        public string $correo,
        public string $curso,
        public string $estado_cuenta,
        public ?string $descripcion,   // Descripción personal
        public array $preferencias, // Devolveremos nombres, no IDs
        public array $idiomas      // Devolveremos nombre y nivel
    ) {}

    /**
     * Mapeador: Convierte la Entidad compleja en este DTO simple
     */
    public static function fromEntity(Voluntario $voluntario): self
    {
        // 1. Aplanar Preferencias (TIPOS DE VOLUNTARIADO)
        // Corrección: Iteramos sobre entidades TipoVoluntariado, no ODS.
        $prefs = [];
        foreach ($voluntario->getPreferencias() as $tipoVoluntariado) {
            // Asumo que en tu entidad TipoVoluntariado el getter es getNombreTipo()
            // porque en BBDD el campo es 'nombre_tipo'
            $prefs[] = $tipoVoluntariado->getNombreTipo();
        }

        // 2. Aplanar Idiomas
        $idiomasList = [];
        foreach ($voluntario->getVoluntarioIdiomas() as $vi) {
            $idiomasList[] = [
                'idioma' => $vi->getIdioma()->getNombre(),
                'nivel'  => $vi->getNivel()
            ];
        }

        // 3. Obtener datos del Usuario padre
        $usuario = $voluntario->getUsuario();

        return new self(
            id_usuario: $usuario->getId(),
            nombre_completo: $voluntario->getNombre() . ' ' . $voluntario->getApellidos(),
            correo: $usuario->getCorreo(),
            // Manejo seguro por si no tiene curso asignado aún
            curso: $voluntario->getCursoActual() ? $voluntario->getCursoActual()->getAbreviacion() : 'Sin asignar',
            estado_cuenta: $usuario->getEstadoCuenta(),
            descripcion: $voluntario->getDescripcion(),
            preferencias: $prefs, // Aquí va el array de strings corregido (ej: ['Salud', 'Educación'])
            idiomas: $idiomasList
        );
    }
}
