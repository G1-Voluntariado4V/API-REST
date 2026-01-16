<?php

namespace App\Model\Inscripcion;

use App\Entity\Inscripcion;

class InscripcionResponseDTO
{
    public function __construct(
        public string $id,              // Composite ID: "voluntario_id-actividad_id"
        public string $estado,          // Pendiente, Aceptada, Rechazada
        public string $fecha_solicitud,

        // Datos de la Actividad (Para el historial del Voluntario)
        public int $id_actividad,
        public string $titulo_actividad,
        public string $descripcion,
        public string $ubicacion,
        public int $duracion_horas,
        public string $fecha_actividad,
        public string $nombre_organizacion,
        public array $ods,
        public array $tipos,

        // Datos del Voluntario (Para la gestión de la ONG)
        public int $id_voluntario,
        public string $nombre_voluntario
    ) {}

    public static function fromEntity(Inscripcion $ins): self
    {
        $act = $ins->getActividad();
        $vol = $ins->getVoluntario();
        $userVol = $vol->getUsuario();

        // Create composite ID from the two primary key components
        $compositeId = $userVol->getId() . '-' . $act->getId();

        // Map ODS
        $ods = [];
        foreach ($act->getOds() as $od) {
            $ods[] = [
                'id' => $od->getId(),
                'nombre' => $od->getNombre()
            ];
        }

        // Map Tipos (Returning strings directly as expected by frontend history)
        $tipos = [];
        foreach ($act->getTiposVoluntariado() as $tipo) {
            $tipos[] = $tipo->getNombreTipo();
        }

        return new self(
            $compositeId,
            $ins->getEstadoSolicitud(),
            $ins->getFechaSolicitud()->format('Y-m-d H:i:s'),

            $act->getId(),
            $act->getTitulo(),
            $act->getDescripcion() ?? '',
            $act->getUbicacion() ?? '',
            $act->getDuracionHoras(),
            $act->getFechaInicio()->format('Y-m-d H:i'),
            $act->getOrganizacion()->getNombre(),
            $ods,
            $tipos,

            $userVol->getId(),
            $vol->getNombre() . ' ' . $vol->getApellidos()
        );
    }
}
