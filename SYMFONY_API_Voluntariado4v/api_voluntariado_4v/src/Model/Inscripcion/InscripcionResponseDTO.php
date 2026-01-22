<?php

namespace App\Model\Inscripcion;

use App\Entity\Inscripcion;

class InscripcionResponseDTO
{
    public function __construct(
        public string $id,
        public string $estado,
        public string $fecha_solicitud,
        public int $id_actividad,
        public string $titulo_actividad,
        public string $descripcion,
        public string $ubicacion,
        public int $duracion_horas,
        public string $fecha_actividad,
        public int $id_organizacion,
        public string $nombre_organizacion,
        public int $cupo_maximo,
        public int $inscritos_confirmados,
        public array $ods,
        public array $tipos,
        public int $id_voluntario,
        public string $nombre_voluntario,
        public ?string $imagen_actividad = null
    ) {}

    public static function fromEntity(Inscripcion $ins): self
    {
        $act = $ins->getActividad();
        $vol = $ins->getVoluntario();
        $userVol = $vol->getUsuario();

        $compositeId = $userVol->getId() . '-' . $act->getId();

        $ods = [];
        foreach ($act->getOds() as $od) {
            $ods[] = [
                'id' => $od->getId(),
                'nombre' => $od->getNombre()
            ];
        }

        $tipos = [];
        foreach ($act->getTiposVoluntariado() as $tipo) {
            $tipos[] = $tipo->getNombreTipo();
        }

        $inscritosConfirmados = 0;
        if ($act->getInscripciones()) {
            foreach ($act->getInscripciones() as $otherIns) {
                if ($otherIns->getEstadoSolicitud() === 'Confirmada' || $otherIns->getEstadoSolicitud() === 'Aceptada') {
                    $inscritosConfirmados++;
                }
            }
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
            $act->getFechaInicio()->format('Y-m-d H:i:s'),
            $act->getOrganizacion()->getId(),
            $act->getOrganizacion()->getNombre(),
            $act->getCupoMaximo(),
            $inscritosConfirmados,
            $ods,
            $tipos,

            $userVol->getId(),
            $vol->getNombre() . ' ' . $vol->getApellidos(),
            $act->getImgActividad()
        );
    }
}
