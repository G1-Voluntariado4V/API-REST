<?php

namespace App\Model\Actividad;

use App\Entity\Actividad;
use App\Model\Ods\OdsDTO;
use App\Model\TipoVoluntariado\TipoVoluntariadoDTO;

class ActividadResponseDTO
{
    public function __construct(
        public int $id,
        public string $titulo,
        public ?string $descripcion,
        public string $fecha_inicio,
        public int $duracion_horas,
        public int $cupo_maximo,
        public int $inscritos_confirmados,
        public int $inscritos_pendientes,
        public string $ubicacion,
        public string $estado_publicacion,
        public int $id_organizacion,
        public string $nombre_organizacion,
        public ?string $img_organizacion,
        /** @var OdsDTO[] */
        public array $ods = [],
        /** @var TipoVoluntariadoDTO[] */
        public array $tipos = [],
        public ?string $imagen_actividad = null
    ) {}

    public static function fromEntity(Actividad $act): self
    {
        $odsList = [];
        foreach ($act->getOds() as $o) {
            $odsList[] = ['id' => $o->getId(), 'nombre' => $o->getNombre()];
        }

        $tiposList = [];
        foreach ($act->getTiposVoluntariado() as $t) {
            $tiposList[] = ['id' => $t->getId(), 'nombre' => $t->getNombreTipo()];
        }

        $org = $act->getOrganizacion();

        $inscritosConfirmados = 0;
        $inscritosPendientes = 0;
        foreach ($act->getInscripciones() as $insc) {
            if ($insc->getEstadoSolicitud() === 'Confirmada' || $insc->getEstadoSolicitud() === 'Aceptada') {
                $inscritosConfirmados++;
            } elseif ($insc->getEstadoSolicitud() === 'Pendiente') {
                $inscritosPendientes++;
            }
        }

        return new self(
            id: $act->getId(),
            titulo: $act->getTitulo(),
            descripcion: $act->getDescripcion(),
            fecha_inicio: $act->getFechaInicio() ? $act->getFechaInicio()->format('Y-m-d H:i:s') : '',
            duracion_horas: $act->getDuracionHoras(),
            cupo_maximo: $act->getCupoMaximo(),
            inscritos_confirmados: $inscritosConfirmados,
            inscritos_pendientes: $inscritosPendientes,
            ubicacion: $act->getUbicacion() ?? 'No definida',
            estado_publicacion: $act->getEstadoPublicacion(),
            id_organizacion: $org ? $org->getId() : 0,
            nombre_organizacion: $org ? $org->getNombre() : 'Desconocida',
            img_organizacion: null,
            ods: $odsList,
            tipos: $tiposList,
            imagen_actividad: $act->getImgActividad()
        );
    }
}
